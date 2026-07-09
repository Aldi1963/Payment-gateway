<?php
/**
 * Unit tests for FeeService — the money-critical fee engine.
 *
 * These run with NO database: FeeService receives an in-memory
 * FakeFeeRuleRepository, and per-channel / provider fee settings are supplied
 * through the guarded test seam in setting() via with_settings().
 */

require_once dirname(__DIR__) . '/Fakes/FakeFeeRuleRepository.php';
require_once dirname(__DIR__, 2) . '/app/Services/FeeService.php';

// -----------------------------------------------------------------------------
// Rule-based calculation (calculateTransaction) — core math
// -----------------------------------------------------------------------------

test('flat fee returns the configured flat amount regardless of value', function () {
    $repo = new FakeFeeRuleRepository(['transaction' => [
        make_fee_rule(['fee_type' => 'flat', 'config' => ['amount' => 2500]]),
    ]]);
    $svc = new FeeService($repo);
    assert_equals(2500, $svc->calculateTransaction(100000, 'm1')['fee']);
    assert_equals(2500, $svc->calculateTransaction(5000, 'm1')['fee']);
});

test('percentage fee = amount * pct / 100 (rounded)', function () {
    $repo = new FakeFeeRuleRepository(['transaction' => [
        make_fee_rule(['fee_type' => 'percentage', 'config' => ['percentage' => 1.5]]),
    ]]);
    $svc = new FeeService($repo);
    assert_equals(1500, $svc->calculateTransaction(100000, 'm1')['fee']);
});

test('percentage fee respects min_fee floor', function () {
    $repo = new FakeFeeRuleRepository(['transaction' => [
        make_fee_rule(['fee_type' => 'percentage', 'config' => ['percentage' => 0.5, 'min_fee' => 2000]]),
    ]]);
    $svc = new FeeService($repo);
    // 100000 * 0.5% = 500 -> floored up to 2000
    assert_equals(2000, $svc->calculateTransaction(100000, 'm1')['fee']);
});

test('percentage fee respects max_fee ceiling', function () {
    $repo = new FakeFeeRuleRepository(['transaction' => [
        make_fee_rule(['fee_type' => 'percentage', 'config' => ['percentage' => 5, 'max_fee' => 3000]]),
    ]]);
    $svc = new FeeService($repo);
    // 100000 * 5% = 5000 -> capped down to 3000
    assert_equals(3000, $svc->calculateTransaction(100000, 'm1')['fee']);
});

test('hybrid fee = percentage + flat', function () {
    $repo = new FakeFeeRuleRepository(['transaction' => [
        make_fee_rule(['fee_type' => 'hybrid', 'config' => ['percentage' => 1, 'flat_amount' => 500]]),
    ]]);
    $svc = new FeeService($repo);
    // 100000 * 1% = 1000 + 500 = 1500
    assert_equals(1500, $svc->calculateTransaction(100000, 'm1')['fee']);
});

test('tier fee selects the matching tier by amount', function () {
    $repo = new FakeFeeRuleRepository(['transaction' => [
        make_fee_rule(['fee_type' => 'tier', 'config' => ['tiers' => [
            ['min_amount' => 0,       'type' => 'flat',       'amount' => 1000],
            ['min_amount' => 100000,  'type' => 'percentage', 'percentage' => 1],
        ]]]),
    ]]);
    $svc = new FeeService($repo);
    assert_equals(1000, $svc->calculateTransaction(50000, 'm1')['fee'], 'below 100k -> flat tier');
    assert_equals(2000, $svc->calculateTransaction(200000, 'm1')['fee'], '>=100k -> 1% tier');
});

test('random fee collapses to min when min >= max (deterministic)', function () {
    $repo = new FakeFeeRuleRepository(['transaction' => [
        make_fee_rule(['fee_type' => 'random', 'config' => ['min_fee' => 1000, 'max_fee' => 1000]]),
    ]]);
    $svc = new FeeService($repo);
    assert_equals(1000, $svc->calculateTransaction(100000, 'm1')['fee']);
});

test('random fee stays within range and aligned to step', function () {
    $repo = new FakeFeeRuleRepository(['transaction' => [
        make_fee_rule(['fee_type' => 'random', 'config' => ['min_fee' => 1000, 'max_fee' => 2000, 'step' => 100]]),
    ]]);
    $svc = new FeeService($repo);
    for ($i = 0; $i < 25; $i++) {
        $fee = $svc->calculateTransaction(100000, 'm1')['fee'];
        assert_true($fee >= 1000 && $fee <= 2000, "fee $fee out of [1000,2000]");
        assert_equals(0, ($fee - 1000) % 100, "fee $fee not aligned to step 100");
    }
});

// -----------------------------------------------------------------------------
// Rule selection: amount range + priority + merchant precedence
// -----------------------------------------------------------------------------

test('rule whose amount range does not match is skipped', function () {
    $repo = new FakeFeeRuleRepository(['transaction' => [
        make_fee_rule(['priority' => 100, 'min_amount' => 500000, 'fee_type' => 'flat', 'config' => ['amount' => 9999]]),
        make_fee_rule(['priority' => 10,  'fee_type' => 'flat', 'config' => ['amount' => 1500]]),
    ]]);
    $svc = new FeeService($repo);
    // amount 100000 < 500000 so first (higher-priority) rule is skipped
    assert_equals(1500, $svc->calculateTransaction(100000, 'm1')['fee']);
});

test('higher priority rule wins when both match', function () {
    $repo = new FakeFeeRuleRepository(['transaction' => [
        make_fee_rule(['priority' => 5,  'fee_type' => 'flat', 'config' => ['amount' => 1000]]),
        make_fee_rule(['priority' => 50, 'fee_type' => 'flat', 'config' => ['amount' => 7000]]),
    ]]);
    $svc = new FeeService($repo);
    assert_equals(7000, $svc->calculateTransaction(100000, 'm1')['fee']);
});

test('merchant custom rule overrides global rule', function () {
    $repo = new FakeFeeRuleRepository(
        ['transaction' => [make_fee_rule(['fee_type' => 'flat', 'config' => ['amount' => 5000]])]],
        ['m1' => ['transaction' => [make_fee_rule(['merchant_id' => 'm1', 'fee_type' => 'flat', 'config' => ['amount' => 500]])]]]
    );
    $svc = new FeeService($repo);
    assert_equals(500, $svc->calculateTransaction(100000, 'm1')['fee'], 'merchant m1 gets custom fee');
    assert_equals(5000, $svc->calculateTransaction(100000, 'm2')['fee'], 'other merchant gets global fee');
});

test('fee result exposes rule_id and snapshot for the matched rule', function () {
    $repo = new FakeFeeRuleRepository(['transaction' => [
        make_fee_rule(['id' => 'rule-abc', 'fee_type' => 'percentage', 'config' => ['percentage' => 2]]),
    ]]);
    $svc = new FeeService($repo);
    $res = $svc->calculateTransaction(100000, 'm1');
    assert_equals('rule-abc', $res['rule_id']);
    assert_equals('percentage', $res['fee_type']);
    assert_true(is_array($res['snapshot']) && $res['snapshot']['rule_id'] === 'rule-abc', 'snapshot carries rule id');
});

// -----------------------------------------------------------------------------
// Zero / negative amounts never produce a negative fee
// -----------------------------------------------------------------------------

test('zero amount with percentage rule yields zero fee', function () {
    $repo = new FakeFeeRuleRepository(['transaction' => [
        make_fee_rule(['fee_type' => 'percentage', 'config' => ['percentage' => 1.5]]),
    ]]);
    $svc = new FeeService($repo);
    assert_equals(0, $svc->calculateTransaction(0, 'm1')['fee']);
});

test('negative amount never produces a negative fee', function () {
    $repo = new FakeFeeRuleRepository(['transaction' => [
        make_fee_rule(['fee_type' => 'percentage', 'config' => ['percentage' => 1.5]]),
    ]]);
    $svc = new FeeService($repo);
    assert_true($svc->calculateTransaction(-100000, 'm1')['fee'] >= 0);
});

// -----------------------------------------------------------------------------
// Channel grouping + Midtrans method mapping (pure static logic)
// -----------------------------------------------------------------------------

test('channelGroupForMethod maps native channels', function () {
    assert_equals('qris',    FeeService::channelGroupForMethod('qris', null));
    assert_equals('va',      FeeService::channelGroupForMethod('va', null));
    assert_equals('ewallet', FeeService::channelGroupForMethod('ewallet', null));
    assert_equals('',        FeeService::channelGroupForMethod('unknown', null));
});

test('channelGroupForMethod maps Midtrans public methods to groups', function () {
    assert_equals('va',      FeeService::channelGroupForMethod('midtrans', 'BCAVA'));
    assert_equals('va',      FeeService::channelGroupForMethod('midtrans', 'BNIVA'));
    assert_equals('ewallet', FeeService::channelGroupForMethod('midtrans', 'GOPAY'));
    assert_equals('ewallet', FeeService::channelGroupForMethod('midtrans', 'SHOPEEPAY'));
    assert_equals('qris',    FeeService::channelGroupForMethod('midtrans', 'MTQRIS'));
    assert_equals('',        FeeService::channelGroupForMethod('midtrans', null));
});

test('mapMidtransPublicToInternal maps known + unknown codes', function () {
    assert_equals('bca_va',       FeeService::mapMidtransPublicToInternal('BCAVA'));
    assert_equals('bni_va',       FeeService::mapMidtransPublicToInternal('BNIVA'));
    assert_equals('gopay',        FeeService::mapMidtransPublicToInternal('GOPAY'));
    assert_equals('qris',         FeeService::mapMidtransPublicToInternal('MTQRIS'));
    assert_equals('mandiri_bill', FeeService::mapMidtransPublicToInternal('MANDIRI'));
    assert_equals(null,           FeeService::mapMidtransPublicToInternal(null));
    assert_equals('foo',          FeeService::mapMidtransPublicToInternal('FOO'), 'unknown falls through lowercased');
});

// -----------------------------------------------------------------------------
// Per-channel platform ("biaya admin") fee
// -----------------------------------------------------------------------------

test('channelPlatformFee flat', function () {
    with_settings(['fee_qris_type' => 'flat', 'fee_qris_value' => 750], function () {
        $svc = new FeeService(new FakeFeeRuleRepository());
        assert_equals(750, $svc->channelPlatformFee(100000, 'qris', 'm1')['fee']);
    });
});

test('channelPlatformFee percentage', function () {
    with_settings(['fee_va_type' => 'percentage', 'fee_va_value' => 1], function () {
        $svc = new FeeService(new FakeFeeRuleRepository());
        assert_equals(1000, $svc->channelPlatformFee(100000, 'va', 'm1')['fee']);
    });
});

test('channelPlatformFee hybrid (percentage + flat)', function () {
    with_settings(['fee_ewallet_type' => 'hybrid', 'fee_ewallet_value' => 1, 'fee_ewallet_flat' => 500], function () {
        $svc = new FeeService(new FakeFeeRuleRepository());
        assert_equals(1500, $svc->channelPlatformFee(100000, 'ewallet', 'm1')['fee']);
    });
});

test('channelPlatformFee falls back to fee engine when channel not configured', function () {
    // No fee_qris_* setting -> falls back to calculateTransaction (global rule)
    $repo = new FakeFeeRuleRepository(['transaction' => [
        make_fee_rule(['fee_type' => 'flat', 'config' => ['amount' => 321]]),
    ]]);
    $svc = new FeeService($repo);
    assert_equals(321, $svc->channelPlatformFee(100000, 'qris', 'm1')['fee']);
});

// -----------------------------------------------------------------------------
// Midtrans provider fee (Midtrans cost, per method)
// -----------------------------------------------------------------------------

test('midtransProviderFee flat', function () {
    with_settings(['mtfee_bca_va_prov_flat' => 4000], function () {
        $svc = new FeeService(new FakeFeeRuleRepository());
        assert_equals(4000, $svc->midtransProviderFee(100000, 'bca_va'));
    });
});

test('midtransProviderFee percentage', function () {
    with_settings(['mtfee_gopay_prov_pct' => 2], function () {
        $svc = new FeeService(new FakeFeeRuleRepository());
        assert_equals(2000, $svc->midtransProviderFee(100000, 'gopay'));
    });
});

test('midtransProviderFee flat + percentage combined', function () {
    with_settings(['mtfee_qris_prov_flat' => 100, 'mtfee_qris_prov_pct' => 0.7], function () {
        $svc = new FeeService(new FakeFeeRuleRepository());
        // 100000 * 0.7% = 700 + 100 = 800
        assert_equals(800, $svc->midtransProviderFee(100000, 'qris'));
    });
});

test('midtransProviderFee is zero for unsupported / empty method', function () {
    with_settings(['mtfee_foo_prov_flat' => 9999], function () {
        $svc = new FeeService(new FakeFeeRuleRepository());
        assert_equals(0, $svc->midtransProviderFee(100000, 'foo'));
        assert_equals(0, $svc->midtransProviderFee(100000, ''));
    });
});

// -----------------------------------------------------------------------------
// calculateForContext: total = platform fee + provider fee
// -----------------------------------------------------------------------------

test('calculateForContext Midtrans VA: total = platform + provider (Rp500 + Rp4000 = Rp4500)', function () {
    with_settings([
        'fee_va_type' => 'flat', 'fee_va_value' => 500,
        'mtfee_bca_va_prov_flat' => 4000,
    ], function () {
        $svc = new FeeService(new FakeFeeRuleRepository());
        $res = $svc->calculateForContext(100000, 'm1', 'midtrans', 'BCAVA');
        assert_equals(4500, $res['fee'], 'total fee');
        assert_equals(500,  $res['snapshot']['platform_fee'], 'platform portion');
        assert_equals(4000, $res['snapshot']['provider_fee'], 'provider portion');
        assert_equals('va', $res['snapshot']['group']);
    });
});

test('calculateForContext native QRIS has no provider fee', function () {
    with_settings(['fee_qris_type' => 'flat', 'fee_qris_value' => 750], function () {
        $svc = new FeeService(new FakeFeeRuleRepository());
        $res = $svc->calculateForContext(100000, 'm1', 'qris', null);
        assert_equals(750, $res['fee']);
        assert_equals(0,   $res['snapshot']['provider_fee']);
    });
});

test('calculateForContext falls back to fee engine when channel/method unknown', function () {
    $repo = new FakeFeeRuleRepository(['transaction' => [
        make_fee_rule(['fee_type' => 'flat', 'config' => ['amount' => 999]]),
    ]]);
    $svc = new FeeService($repo);
    assert_equals(999, $svc->calculateForContext(100000, 'm1', null, null)['fee']);
});

// -----------------------------------------------------------------------------
// Withdrawal fee + net amount
// -----------------------------------------------------------------------------

test('withdrawal fee: none by default', function () {
    with_settings(['withdrawal_fee_type' => 'none'], function () {
        $svc = new FeeService(new FakeFeeRuleRepository());
        assert_equals(0, $svc->calculateWithdrawal(100000, 'm1')['fee']);
    });
});

test('withdrawal fee: flat setting', function () {
    with_settings(['withdrawal_fee_type' => 'flat', 'withdrawal_fee_value' => 2500], function () {
        $svc = new FeeService(new FakeFeeRuleRepository());
        assert_equals(2500, $svc->calculateWithdrawal(100000, 'm1')['fee']);
    });
});

test('simulate reports net_amount = amount - fee', function () {
    $repo = new FakeFeeRuleRepository(['transaction' => [
        make_fee_rule(['fee_type' => 'flat', 'config' => ['amount' => 2500]]),
    ]]);
    $svc = new FeeService($repo);
    $sim = $svc->simulate(100000, 'transaction', 'm1');
    assert_equals(2500,  $sim['fee']);
    assert_equals(97500, $sim['net_amount']);
});
