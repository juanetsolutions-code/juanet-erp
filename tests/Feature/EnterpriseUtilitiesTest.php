<?php

namespace Tests\Feature;

use App\Helpers\MoneyHelper;
use App\Helpers\DateHelper;
use App\Helpers\UuidHelper;
use App\Helpers\ResponseBuilder;
use App\Helpers\PaginationHelper;
use App\Helpers\ValidationHelper;
use App\Helpers\FileHelper;
use App\Helpers\StringHelper;
use App\Helpers\ArrayHelper;
use App\Helpers\CollectionHelper;
use App\Helpers\TimezoneHelper;
use App\Helpers\CurrencyHelper;
use App\Helpers\NumberFormatter;
use App\Helpers\ActivityHelper;
use App\Helpers\ExceptionHelper;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;

test('MoneyHelper handles conversions and high precision arithmetic', function () {
    // 1. Cents conversions
    expect(MoneyHelper::toCents(123.45))->toBe(12345);
    expect(MoneyHelper::toDecimal(12345))->toBe(123.45);

    // 2. Arithmetic
    expect(MoneyHelper::add('10.25', '5.50'))->toBe(15.75);
    expect(MoneyHelper::subtract('10.00', '3.25'))->toBe(6.75);
    expect(MoneyHelper::multiply('12.50', 2))->toBe(25.00);
    expect(MoneyHelper::divide('100.00', 3))->toBe(33.33);

    // 3. Lossless allocation
    $shares = MoneyHelper::allocate(5, [1, 1]); // Allocate 5 cents across 2 equal shares
    expect($shares)->toBe([3, 2]);

    $shares = MoneyHelper::allocate(100, [1, 2, 1]); // Allocate 100 cents across 1:2:1 ratio (25, 50, 25)
    expect($shares)->toBe([25, 50, 25]);

    // 4. Formatting
    expect(MoneyHelper::format(1250.75, 'USD'))->toBe('$1,250.75');
    expect(MoneyHelper::format(500, 'KES'))->toBe('KSh 500.00');
});

test('DateHelper processes business day math and fiscal quarters', function () {
    // 1. Weekend check
    expect(DateHelper::isWeekend('2026-07-04'))->toBeTrue(); // Saturday
    expect(DateHelper::isWeekend('2026-07-06'))->toBeFalse(); // Monday

    // 2. Business days between
    // Monday 2026-07-06 to Friday 2026-07-10 is 5 business days
    expect(DateHelper::businessDaysBetween('2026-07-06', '2026-07-10'))->toBe(5);

    // Monday 2026-07-06 to Friday 2026-07-10 with a holiday on Wednesday 2026-07-08 is 4 business days
    expect(DateHelper::businessDaysBetween('2026-07-06', '2026-07-10', ['2026-07-08']))->toBe(4);

    // 3. Add business days
    $resultDate = DateHelper::addBusinessDays('2026-07-03', 3); // Starts Friday, add 3 business days -> Mon, Tue, Wed (July 8)
    expect($resultDate->format('Y-m-d'))->toBe('2026-07-08');

    // 4. Fiscal Quarter
    $quarterInfo = DateHelper::getFiscalQuarter('2026-07-15'); // July is Q3
    expect($quarterInfo['quarter'])->toBe(3);
    expect($quarterInfo['start']->format('Y-m-d'))->toBe('2026-07-01');
    expect($quarterInfo['end']->format('Y-m-d'))->toBe('2026-09-30');

    // 5. Date Overlap
    expect(DateHelper::rangesOverlap('2026-07-01', '2026-07-10', '2026-07-05', '2026-07-15'))->toBeTrue();
    expect(DateHelper::rangesOverlap('2026-07-01', '2026-07-04', '2026-07-05', '2026-07-15'))->toBeFalse();
});

test('UuidHelper generates and parses ordered UUID v7 structures', function () {
    // 1. Generation
    $v4 = UuidHelper::v4();
    $v7 = UuidHelper::v7();

    expect(UuidHelper::isValid($v4))->toBeTrue();
    expect(UuidHelper::isValid($v7))->toBeTrue();
    expect($v4)->not->toBe($v7);

    // 2. Extract timestamp from UUID v7
    $timestamp = UuidHelper::extractTimestamp($v7);
    expect($timestamp)->toBeInstanceOf(Carbon::class);
    expect($timestamp->isToday())->toBeTrue();

    // v4 has no extractable timestamp
    expect(UuidHelper::extractTimestamp($v4))->toBeNull();
});

test('ResponseBuilder constructs uniform API payloads', function () {
    $response = ResponseBuilder::success(['id' => 1], 'Retrieved');
    $content = json_decode($response->getContent(), true);

    expect($response->getStatusCode())->toBe(200);
    expect($content['success'])->toBeTrue();
    expect($content['message'])->toBe('Retrieved');
    expect($content['data']['id'])->toBe(1);

    $responseErr = ResponseBuilder::error('Unauthorized access', 'AUTH_FORBIDDEN', ['scope' => 'admin'], 403);
    $contentErr = json_decode($responseErr->getContent(), true);

    expect($responseErr->getStatusCode())->toBe(403);
    expect($contentErr['success'])->toBeFalse();
    expect($contentErr['error']['code'])->toBe('AUTH_FORBIDDEN');
    expect($contentErr['error']['message'])->toBe('Unauthorized access');
    expect($contentErr['error']['details'])->toBe(['scope' => 'admin']);
});

test('PaginationHelper formats Eloquent pagination models', function () {
    $items = collect([['id' => 1], ['id' => 2]]);
    $paginator = new LengthAwarePaginator($items, 10, 2, 1, [
        'path' => 'https://api.juanet.io/v1/users',
    ]);

    $meta = PaginationHelper::getMeta($paginator);

    expect($meta['total'])->toBe(10);
    expect($meta['per_page'])->toBe(2);
    expect($meta['current_page'])->toBe(1);
    expect($meta['count'])->toBe(2);
    expect($meta['total_pages'])->toBe(5);
    expect($meta['links']['next'])->toContain('page=2');
});

test('ValidationHelper verifies emails, passwords, tax PINs, and IP masks', function () {
    // 1. Corporate Domain
    expect(ValidationHelper::isCorporateEmail('cto@juanet.io'))->toBeTrue();
    expect(ValidationHelper::isCorporateEmail('user@gmail.com'))->toBeFalse();

    // 2. Password Strength
    expect(ValidationHelper::checkPasswordStrength('Pass123!'))->toBeTrue();
    
    $failed = ValidationHelper::checkPasswordStrength('pass');
    expect($failed)->toBeArray();
    expect(array_keys($failed))->toContain('length', 'uppercase', 'number', 'special');

    // 3. E.164 Phone
    expect(ValidationHelper::isValidE164Phone('+254712345678'))->toBeTrue();
    expect(ValidationHelper::isValidE164Phone('0712345678'))->toBeFalse();

    // 4. KRA Tax PIN
    expect(ValidationHelper::isValidKraPin('A012345678B'))->toBeTrue();
    expect(ValidationHelper::isValidKraPin('0123456789B'))->toBeFalse();

    // 5. IP CIDR Matching
    expect(ValidationHelper::ipMatchesCidr('192.168.1.50', '192.168.1.0/24'))->toBeTrue();
    expect(ValidationHelper::ipMatchesCidr('10.0.0.5', '192.168.1.0/24'))->toBeFalse();
});

test('FileHelper processes file sizes, categories, and safe names', function () {
    // 1. Raw to Human representation
    expect(FileHelper::formatBytes(1024))->toBe('1.00 KB');
    expect(FileHelper::formatBytes(1048576 * 2.5))->toBe('2.50 MB');

    // 2. Human shorthand to absolute Bytes
    expect(FileHelper::parseToBytes('10M'))->toBe(10485760);
    expect(FileHelper::parseToBytes('2G'))->toBe(2147483648);
    expect(FileHelper::parseToBytes('512K'))->toBe(524288);

    // 3. Categories mapping
    expect(FileHelper::getCategory('portrait.png'))->toBe('image');
    expect(FileHelper::getCategory('invoice.pdf'))->toBe('document');
    expect(FileHelper::getCategory('archive.7z'))->toBe('archive');
    expect(FileHelper::getCategory('script.py'))->toBe('code');

    // 4. Safe Filename generator
    $safe = FileHelper::makeSafeFilename('Monthly Report #2026.xlsx', 'tenant_1');
    expect($safe)->toContain('tenant_1_monthly_report_2026_');
    expect(Str::endsWith($safe, '.xlsx'))->toBeTrue();
});

test('StringHelper handles masks, truncation, and secure random generators', function () {
    // 1. Email masking
    expect(StringHelper::maskEmail('juanet.solutions@gmail.com'))->toBe('j*****************s@gmail.com');
    expect(StringHelper::maskEmail('me@io.com'))->toBe('m*@io.com');

    // 2. Phone masking
    expect(StringHelper::maskPhone('+254712345678'))->toBe('+2547******78');

    // 3. Clean truncation
    $longText = 'This is an enterprise SaaS platform with advanced architectural compliance metrics';
    expect(StringHelper::truncateWords($longText, 25))->toBe('This is an enterprise...');

    // 4. Secure Random generator
    $codeNum = StringHelper::secureRandomCode(6, 'numeric');
    expect(strlen($codeNum))->toBe(6);
    expect(is_numeric($codeNum))->toBeTrue();

    $codeComplex = StringHelper::secureRandomCode(12, 'complex');
    expect(strlen($codeComplex))->toBe(12);
});

test('ArrayHelper processes multi-dimensional structural manipulation', function () {
    $nested = [
        'app' => [
            'name' => 'JUANET',
            'config' => [
                'port' => 3000,
            ],
        ],
    ];

    // 1. Flatten
    $flat = ArrayHelper::flatten($nested);
    expect($flat['app.name'])->toBe('JUANET');
    expect($flat['app.config.port'])->toBe(3000);

    // 2. Expand
    $expanded = ArrayHelper::expand($flat);
    expect($expanded['app']['name'])->toBe('JUANET');
    expect($expanded['app']['config']['port'])->toBe(3000);

    // 3. Group By
    $list = [
        ['id' => 1, 'role' => 'admin'],
        ['id' => 2, 'role' => 'user'],
        ['id' => 3, 'role' => 'admin'],
    ];
    $grouped = ArrayHelper::groupBy($list, 'role');
    expect(count($grouped['admin']))->toBe(2);
    expect(count($grouped['user']))->toBe(1);

    // 4. Sort By Nested Key
    $unsorted = [
        ['profile' => ['age' => 40]],
        ['profile' => ['age' => 20]],
        ['profile' => ['age' => 30]],
    ];
    $sorted = ArrayHelper::sortByNestedKey($unsorted, 'profile.age');
    expect($sorted[0]['profile']['age'])->toBe(20);
    expect($sorted[2]['profile']['age'])->toBe(40);

    // 5. Deep Merge
    $base = ['db' => ['host' => 'localhost', 'port' => 3306]];
    $override = ['db' => ['port' => 5432, 'user' => 'postgres']];
    $merged = ArrayHelper::deepMerge($base, $override);

    expect($merged['db']['host'])->toBe('localhost');
    expect($merged['db']['port'])->toBe(5432);
    expect($merged['db']['user'])->toBe('postgres');
});

test('CollectionHelper and UtilityServiceProvider register macros', function () {
    // 1. Collection Manual Pagination Macro
    $items = collect([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);
    $paginator = $items->paginate(3, 2);

    expect($paginator)->toBeInstanceOf(LengthAwarePaginator::class);
    expect($paginator->total())->toBe(10);
    expect($paginator->perPage())->toBe(3);
    expect($paginator->currentPage())->toBe(2);
    expect($paginator->items())->toBe([4, 5, 6]);

    // 2. Collection Distribution Macro
    $distributed = $items->distribute(3);
    expect($distributed->count())->toBe(3);
    expect($distributed->get(0)->toArray())->toBe([1, 4, 7, 10]);
    expect($distributed->get(1)->toArray())->toBe([2, 5, 8]);
    expect($distributed->get(2)->toArray())->toBe([3, 6, 9]);

    // 3. Str Masking Macros
    expect(Str::maskEmail('juanet.solutions@gmail.com'))->toBe('j*****************s@gmail.com');
    expect(Str::maskPhone('+254712345678'))->toBe('+2547******78');
});

test('TimezoneHelper converts between regions and offsets', function () {
    // 1. Validity Check
    expect(TimezoneHelper::isValid('Africa/Nairobi'))->toBeTrue();
    expect(TimezoneHelper::isValid('Mars/Base'))->toBeFalse();

    // 2. Converters
    $converted = TimezoneHelper::convert('2026-07-02 12:00:00', 'UTC', 'Africa/Nairobi');
    expect($converted)->toBe('2026-07-02 15:00:00'); // +3 hrs

    $toUtc = TimezoneHelper::toUtc('2026-07-02 15:00:00', 'Africa/Nairobi');
    expect($toUtc)->toBe('2026-07-02 12:00:00');

    $toLocal = TimezoneHelper::toLocal('2026-07-02 12:00:00', 'Africa/Nairobi');
    expect($toLocal)->toBe('2026-07-02 15:00:00');

    // 3. Offset Formats
    expect(TimezoneHelper::getOffset('Africa/Nairobi'))->toBe('+03:00');
    expect(TimezoneHelper::getOffset('America/New_York'))->toBe('-04:00'); // DST July

    // 4. Grouped selection
    $groupedList = TimezoneHelper::listGrouped();
    expect(array_keys($groupedList))->toContain('Africa', 'Europe', 'America');
});

test('CurrencyHelper parses active catalog codes', function () {
    // 1. Supported Check
    expect(CurrencyHelper::isSupported('USD'))->toBeTrue();
    expect(CurrencyHelper::isSupported('KES'))->toBeTrue();
    expect(CurrencyHelper::isSupported('ZIM'))->toBeFalse();

    // 2. Exchange math
    $usdAmount = CurrencyHelper::convert(100, 130.50, 'KES'); // Convert $100 with KES rate 130.5
    expect($usdAmount)->toBe(13050.0);

    // 3. Metadata
    $meta = CurrencyHelper::getMetadata('KES');
    expect($meta['symbol'])->toBe('KSh');

    // 4. Custom Layout Formatting
    expect(CurrencyHelper::formatCustom(12000, 'KES'))->toBe('KSh 12,000.00');
    expect(CurrencyHelper::formatCustom(12000, 'USD'))->toBe('$12,000.00');
});

test('NumberFormatter compacts digits and builds ordinals', function () {
    // 1. Compact
    expect(NumberFormatter::compact(950))->toBe('950');
    expect(NumberFormatter::compact(1200))->toBe('1.2K');
    expect(NumberFormatter::compact(2450000))->toBe('2.5M');
    expect(NumberFormatter::compact(5100000000))->toBe('5.1B');

    // 2. Ordinal
    expect(NumberFormatter::ordinal(1))->toBe('1st');
    expect(NumberFormatter::ordinal(22))->toBe('22nd');
    expect(NumberFormatter::ordinal(103))->toBe('103rd');
    expect(NumberFormatter::ordinal(11))->toBe('11th');

    // 3. Percentage
    expect(NumberFormatter::percentage(23.45))->toBe('23.45%');
    expect(NumberFormatter::percentage(0.2345, 2, true))->toBe('23.45%');
});

test('ActivityHelper packages dynamic request context', function () {
    $context = ActivityHelper::buildContext();
    
    expect($context)->toHaveKeys(['ip_address', 'user_agent', 'user_id', 'timestamp']);
    expect($context['ip_address'])->toBe('127.0.0.1');

    $desc = ActivityHelper::formatDescription("User {name} processed payment {ref}", [
        'name' => 'John Doe',
        'ref' => 'PAY-901',
    ]);
    expect($desc)->toBe('User John Doe processed payment PAY-901');

    expect(ActivityHelper::moduleLabel('billing'))->toBe('Billing & Subscriptions');
});

test('ExceptionHelper handles diagnostic mapping', function () {
    $authException = new AuthorizationException('Clearance missing.');
    
    $friendly = ExceptionHelper::getFriendlyMessage($authException);
    expect($friendly)->toBe('You do not possess the necessary administrative clearance for this operation.');

    $genericException = new \RuntimeException('General system error.');
    expect(ExceptionHelper::isSystemFatal($genericException))->toBeTrue();

    $array = ExceptionHelper::toArray($authException, true);
    expect($array['message'])->toBe('You do not possess the necessary administrative clearance for this operation.');
    expect($array['type'])->toBe(AuthorizationException::class);
    expect($array)->toHaveKeys(['file', 'line', 'trace']);
});
