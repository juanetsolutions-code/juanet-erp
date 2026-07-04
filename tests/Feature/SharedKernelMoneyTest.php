<?php

namespace Tests\Feature;

use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Shared\ValueObjects\Currency;
use App\Domain\Shared\Exceptions\CurrencyMismatchException;
use App\Domain\Shared\Services\MoneyCalculator;
use App\Domain\Shared\Services\LocaleMoneyFormatter;
use App\Domain\Shared\Contracts\MoneyFormatter;
use App\Domain\Shared\Casts\MoneyCast;
use Tests\TestCase;
use InvalidArgumentException;
use BadMethodCallException;

/**
 * Enterprise Money Value Object Test Suite.
 *
 * Verifies mathematical precision, multi-currency mismatch safety, Bankers Rounding,
 * proportional remainder allocations, tax/discount computations, and serialization.
 */
class SharedKernelMoneyTest extends TestCase
{
    /**
     * Verify instantiation from multiple types.
     */
    public function test_can_instantiate_money_from_various_types()
    {
        // 1. From float/double via magic helper
        $m1 = Money::USD(1500.55);
        $this->assertEquals('1500.55', $m1->getAmount());
        $this->assertEquals(150055, $m1->getMinorUnits());
        $this->assertEquals('USD', $m1->getCurrency()->getCode());

        // 2. From decimal string via static constructor
        $m2 = Money::fromDecimal('25.99', 'KES');
        $this->assertEquals('25.99', $m2->getAmount());
        $this->assertEquals(2599, $m2->getMinorUnits());

        // 3. From minor units (integers)
        $m3 = Money::fromMinorUnits(2500, 'USD');
        $this->assertEquals('25.00', $m3->getAmount());
        $this->assertEquals(2500, $m3->getMinorUnits());

        // 4. Zero precision currency (JPY) from minor units
        $m4 = Money::fromMinorUnits(2500, 'JPY');
        $this->assertEquals('2500', $m4->getAmount());
        $this->assertEquals(2500, $m4->getMinorUnits());

        // 5. From Database decimal string
        $m5 = Money::fromDatabase('100.123', 'USD'); // banker's rounds to 100.12
        $this->assertEquals('100.12', $m5->getAmount());
        $this->assertEquals(10012, $m5->getMinorUnits());
    }

    /**
     * Verify validation for dynamic calls.
     */
    public function test_invalid_currency_code_throws_exception()
    {
        $this->expectException(BadMethodCallException::class);
        Money::INVALID_CODE(100);
    }

    /**
     * Verify addition operation and currency mismatch checks.
     */
    public function test_addition_correctness_and_safety()
    {
        $usd1 = Money::USD('100.50');
        $usd2 = Money::USD('200.25');

        $result = $usd1->add($usd2);
        $this->assertEquals('300.75', $result->getAmount());
        $this->assertNotSame($usd1, $result); // Immutability check

        // Currency mismatch verification
        $kes = Money::KES('100.00');
        $this->expectException(CurrencyMismatchException::class);
        $usd1->add($kes);
    }

    /**
     * Verify subtraction operation and currency mismatch checks.
     */
    public function test_subtraction_correctness_and_safety()
    {
        $usd1 = Money::USD('500.00');
        $usd2 = Money::USD('120.50');

        $result = $usd1->subtract($usd2);
        $this->assertEquals('379.50', $result->getAmount());
        $this->assertNotSame($usd1, $result);

        // Currency mismatch verification
        $kes = Money::KES('100.00');
        $this->expectException(CurrencyMismatchException::class);
        $usd1->subtract($kes);
    }

    /**
     * Verify multiplication.
     */
    public function test_multiplication_correctness()
    {
        $money = Money::USD('15.55');
        $result = $money->multiply(3);
        $this->assertEquals('46.65', $result->getAmount());
    }

    /**
     * Verify division.
     */
    public function test_division_correctness()
    {
        $money = Money::USD('100.00');
        $result = $money->divide('3');
        $this->assertEquals('33.33', $result->getAmount()); // Banker's Rounded

        $this->expectException(InvalidArgumentException::class);
        $money->divide(0);
    }

    /**
     * Verify percentage calculations.
     */
    public function test_percentage_calculation()
    {
        $money = Money::USD('250.00');
        $result = $money->percentage(15); // 15% of 250 is 37.5
        $this->assertEquals('37.50', $result->getAmount());
    }

    /**
     * Verify discount calculation engine.
     */
    public function test_discount_engine()
    {
        $money = Money::USD('100.00');

        // 1. Percentage discount (15% off 100) -> 85
        $disc1 = $money->discount(15, 'percentage');
        $this->assertEquals('85.00', $disc1->getAmount());

        // 2. Fixed discount ($25 off 100) -> 75
        $disc2 = $money->discount(25, 'fixed');
        $this->assertEquals('75.00', $disc2->getAmount());

        // 3. Fixed discount using another Money object
        $disc3 = $money->discount(Money::USD('35.50'), 'fixed');
        $this->assertEquals('64.50', $disc3->getAmount());
    }

    /**
     * Verify tax calculations.
     */
    public function test_tax_calculations()
    {
        $money = Money::USD('100.00');

        // 1. Exclusive Tax (e.g. 16% VAT on $100 base) -> Tax amount is $16
        $taxExclusive = $money->exclusiveTax(0.16);
        $this->assertEquals('16.00', $taxExclusive->getAmount());

        // 2. Inclusive Tax (e.g. $100 price includes 16% VAT) -> Base = 100 / 1.16 = 86.206... -> rounded Base = 86.21. Tax portion = 13.79
        $taxInclusive = $money->inclusiveTax(0.16);
        $this->assertEquals('13.79', $taxInclusive->getAmount());

        // 3. Remove Tax (Extracting original base price) -> Base = 100 / 1.16 = 86.21
        $base = $money->removeTax(0.16);
        $this->assertEquals('86.21', $base->getAmount());

        // 4. Tax amount general helper
        $taxAmtInc = $money->taxAmount(0.16, true);
        $this->assertEquals('13.79', $taxAmtInc->getAmount());

        $taxAmtExc = $money->taxAmount(0.16, false);
        $this->assertEquals('16.00', $taxAmtExc->getAmount());
    }

    /**
     * Verify Banker's Rounding (Round Half to Even) and other modes.
     */
    public function test_rounding_modes_accuracy()
    {
        // Banker's Rounding (default) is Round Half to Even.
        // It rounds ties to the nearest even number.
        
        // 2.25 rounds to 2.2 (first decimal place)
        $this->assertEquals('2.2', MoneyCalculator::round('2.25', 1, MoneyCalculator::ROUND_BANKERS));
        // 2.35 rounds to 2.4 (first decimal place)
        $this->assertEquals('2.4', MoneyCalculator::round('2.35', 1, MoneyCalculator::ROUND_BANKERS));

        // 2.251 has non-zero rest, so it is strictly greater than half, rounding up to 2.3
        $this->assertEquals('2.3', MoneyCalculator::round('2.251', 1, MoneyCalculator::ROUND_BANKERS));

        // Test other rounding modes
        $val = '12.345'; // target prec 2

        // Half Up: rounds ties away from zero -> 12.35
        $this->assertEquals('12.35', MoneyCalculator::round($val, 2, MoneyCalculator::ROUND_HALF_UP));

        // Half Down: rounds ties towards zero -> 12.34
        $this->assertEquals('12.34', MoneyCalculator::round($val, 2, MoneyCalculator::ROUND_HALF_DOWN));

        // Floor: rounds towards negative infinity
        $this->assertEquals('12.34', MoneyCalculator::round($val, 2, MoneyCalculator::ROUND_FLOOR));
        $this->assertEquals('-12.35', MoneyCalculator::round('-12.345', 2, MoneyCalculator::ROUND_FLOOR));

        // Ceiling: rounds towards positive infinity
        $this->assertEquals('12.35', MoneyCalculator::round($val, 2, MoneyCalculator::ROUND_CEILING));
        $this->assertEquals('-12.34', MoneyCalculator::round('-12.345', 2, MoneyCalculator::ROUND_CEILING));

        // Truncate: chops decimals -> 12.34
        $this->assertEquals('12.34', MoneyCalculator::round($val, 2, MoneyCalculator::ROUND_TRUNCATE));
    }

    /**
     * Verify proportional allocations without any leaks (perfect reconciliation).
     */
    public function test_proportional_allocation()
    {
        $money = Money::KES('100.00'); // 10000 minor units
        
        // Allocate equally to 3 parts -> 33.34, 33.33, 33.33
        $parts = $money->allocate([1, 1, 1]);
        
        $this->assertCount(3, $parts);
        $this->assertEquals('33.34', $parts[0]->getAmount());
        $this->assertEquals('33.33', $parts[1]->getAmount());
        $this->assertEquals('33.33', $parts[2]->getAmount());

        // Reconcile total
        $sum = $parts[0]->add($parts[1])->add($parts[2]);
        $this->assertEquals('100.00', $sum->getAmount());

        // Allocate with unequal ratios (e.g. 30%, 40%, 30%)
        $parts2 = $money->allocate([30, 40, 30]);
        $this->assertEquals('30.00', $parts2[0]->getAmount());
        $this->assertEquals('40.00', $parts2[1]->getAmount());
        $this->assertEquals('30.00', $parts2[2]->getAmount());
    }

    /**
     * Verify financial comparisons.
     */
    public function test_financial_comparisons()
    {
        $usd1 = Money::USD('150.00');
        $usd2 = Money::USD('150.00');
        $usd3 = Money::USD('200.00');
        $usd4 = Money::USD('50.00');

        $this->assertTrue($usd1->equals($usd2));
        $this->assertTrue($usd3->greaterThan($usd1));
        $this->assertTrue($usd4->lessThan($usd1));
        $this->assertTrue($usd1->between($usd4, $usd3));

        $zero = Money::USD('0.00');
        $positive = Money::USD('1.50');
        $negative = Money::USD('-1.50');

        $this->assertTrue($zero->isZero());
        $this->assertTrue($positive->isPositive());
        $this->assertTrue($negative->isNegative());

        // Min / Max
        $this->assertEquals('50.00', $usd1->min($usd4)->getAmount());
        $this->assertEquals('200.00', $usd1->max($usd3)->getAmount());

        // Absolute and negation
        $this->assertEquals('1.50', $negative->absolute()->getAmount());
        $this->assertEquals('-1.50', $positive->negate()->getAmount());
    }

    /**
     * Verify locale-aware formatting.
     */
    public function test_locale_aware_formatting()
    {
        $formatter = new LocaleMoneyFormatter();

        $usd = Money::USD('15000.50');
        $kes = Money::KES('250000.00');
        $eur = Money::EUR('99.90');

        $this->assertEquals('$15,000.50', $formatter->format($usd));
        $this->assertEquals('KES 250,000.00', $formatter->format($kes));
        $this->assertEquals('€99.90', $formatter->format($eur));
    }

    /**
     * Verify JSON Serialization correctness.
     */
    public function test_json_serialization()
    {
        $money = Money::KES('1500.00');
        $json = json_encode($money);
        $data = json_decode($json, true);

        $this->assertEquals(1500.0, $data['amount']);
        $this->assertEquals('KES', $data['currency']);
        $this->assertEquals('KES 1,500.00', $data['formatted']);
        $this->assertEquals(150000, $data['minor_units']);
    }

    /**
     * Verify Eloquent Cast Database serialization and deserialization.
     */
    public function test_eloquent_database_serialization()
    {
        $cast = new MoneyCast();

        // 1. Dual-column matching
        $attributes = ['amount_currency' => 'EUR', 'amount' => '99.95'];
        $money = $cast->get(null, 'amount', '99.95', $attributes);
        $this->assertInstanceOf(Money::class, $money);
        $this->assertEquals('99.95', $money->getAmount());
        $this->assertEquals('EUR', $money->getCurrency()->getCode());

        // 2. Compound string handling
        $moneyCompound = $cast->get(null, 'price', 'USD 120.50', []);
        $this->assertEquals('120.50', $moneyCompound->getAmount());
        $this->assertEquals('USD', $moneyCompound->getCurrency()->getCode());

        // 3. Dual-column serialization on set
        $result = $cast->set(null, 'amount', Money::USD('25.99'), ['amount_currency' => 'USD']);
        $this->assertIsArray($result);
        $this->assertEquals('25.99', $result['amount']);
        $this->assertEquals('USD', $result['amount_currency']);

        // 4. Fallback compound string serialization on set
        $resultFallback = $cast->set(null, 'price', Money::GBP('50.00'), []);
        $this->assertEquals('GBP 50.00', $resultFallback);
    }

    /**
     * Verify handling of very large values.
     */
    public function test_very_large_monetary_values()
    {
        $largeValue = '999999999999999999.99'; // 18-digit integer part
        $money = Money::USD($largeValue);
        $this->assertEquals($largeValue, $money->getAmount());

        $added = $money->add(Money::USD('0.01'));
        $this->assertEquals('1000000000000000000.00', $added->getAmount());
    }

    /**
     * Verify immutability and concurrency/isolation safety.
     */
    public function test_immutability_and_safety()
    {
        $original = Money::USD('100.00');
        $added = $original->add(Money::USD('50.00'));

        $this->assertEquals('100.00', $original->getAmount());
        $this->assertEquals('150.00', $added->getAmount());
        $this->assertNotSame($original, $added);
    }
}
