<?php

namespace Tests\Unit;

use App\Services\CnicParserService;
use Tests\TestCase;

class CnicParserServiceTest extends TestCase
{
    public function test_it_parses_fixture_ocr_text(): void
    {
        $text = (string) file_get_contents(base_path('tests/Fixtures/ocr/sample-cnic.txt'));
        $parsed = (new CnicParserService)->parse($text);

        $this->assertSame('35202-1234567-1', $parsed->cnic);
        $this->assertSame('ALI RAZA', $parsed->name);
        $this->assertSame('MUHAMMAD RAZA', $parsed->fatherHusbandName);
        $this->assertSame('HOUSE 12, STREET 4, GULBERG, LAHORE', $parsed->address);
    }

    public function test_it_handles_malformed_ocr_text(): void
    {
        $text = (string) file_get_contents(base_path('tests/Fixtures/ocr/malformed.txt'));
        $parsed = (new CnicParserService)->parse($text);

        $this->assertNull($parsed->cnic);
        $this->assertNull($parsed->name);
        $this->assertNull($parsed->fatherHusbandName);
        $this->assertNull($parsed->address);
    }

    public function test_it_parses_noisy_tesseract_cnic_text(): void
    {
        $text = <<<'TEXT'
ISLAMIC REPUBLIC OF PAKISTAN
Name HAMZA BAIG
Father Name MUSHTAQ BAIG
Gender Male
Country of Stay Pakistan
Identity Number 13301-4455667-8
Date of Birth 07.03.2001
Address HOUSE 19, STREET 8, PESHAWAR
TEXT;

        $parsed = (new CnicParserService)->parse($text);

        $this->assertSame('13301-4455667-8', $parsed->cnic);
        $this->assertSame('HAMZA BAIG', $parsed->name);
        $this->assertSame('MUSHTAQ BAIG', $parsed->fatherHusbandName);
        $this->assertSame('HOUSE 19, STREET 8, PESHAWAR', $parsed->address);
    }

    public function test_it_recovers_ocr_digit_confusions_in_cnic(): void
    {
        $parsed = (new CnicParserService)->parse("Identity Number\nI3301-O123456-l");

        $this->assertSame('13301-0123456-1', $parsed->cnic);
    }

    public function test_it_parses_cropped_tesseract_layout(): void
    {
        $text = <<<'TEXT'
Name
al Hamza Test
Father Name
Mushtaq Test
Identity Number 35202-1234567-19070
TEXT;

        $parsed = (new CnicParserService)->parse($text);

        $this->assertSame('35202-1234567-1', $parsed->cnic);
        $this->assertSame('Hamza Test', $parsed->name);
        $this->assertSame('Mushtaq Test', $parsed->fatherHusbandName);
    }

    public function test_it_skips_header_noise_after_father_name(): void
    {
        $text = <<<'TEXT'
Name
Hamza Test
Father Name
a By ; PAKISTAN National Identity
ISLAMIC REPUBLIC OF PAKISTAN
Father Name
oe Mushtaq Test
Identity Number
35202-1234567-1
TEXT;

        $parsed = (new CnicParserService)->parse($text);

        $this->assertSame('Hamza Test', $parsed->name);
        $this->assertSame('Mushtaq Test', $parsed->fatherHusbandName);
    }

    public function test_it_strips_ocr_junk_from_person_names(): void
    {
        $text = <<<'TEXT'
Name
a. = Hamza Test
Father Name
Mushtaq Test
35202-1234567-1
TEXT;

        $parsed = (new CnicParserService)->parse($text);

        $this->assertSame('Hamza Test', $parsed->name);
        $this->assertSame('Mushtaq Test', $parsed->fatherHusbandName);
    }

    public function test_it_parses_same_line_labels_and_unicode(): void
    {
        $text = <<<'TEXT'
Name: فاطمہ خان
Father Name: احمد خان
Identity Number: 37405-1111111-2
Address: مکان ۱۲، اسلام آباد
TEXT;

        $parsed = (new CnicParserService)->parse($text);

        $this->assertSame('37405-1111111-2', $parsed->cnic);
        $this->assertSame('فاطمہ خان', $parsed->name);
        $this->assertSame('احمد خان', $parsed->fatherHusbandName);
        $this->assertSame('مکان ۱۲، اسلام آباد', $parsed->address);
    }

    public function test_it_extracts_urdu_address_from_the_back_text(): void
    {
        $front = <<<'TEXT'
Name
ALI RAZA
Father Name
MUHAMMAD RAZA
35202-1234567-1
TEXT;
        $back = <<<'TEXT'
موجودہ پتہ
خانہ نمبر ۱۲ گلی ۴ گلبگ لاہور
مستقل پتہ
خانہ نمبر ۱۲ گلی ۴ گلبگ لاہور
TEXT;

        $parsed = (new CnicParserService)->parse($front, $back);

        $this->assertSame('ALI RAZA', $parsed->name);
        $this->assertSame('35202-1234567-1', $parsed->cnic);
        $this->assertStringContainsString('گلبگ لاہور', (string) $parsed->address);
    }

    public function test_it_parses_english_back_address_and_mrz(): void
    {
        $front = <<<'TEXT'
Islamic Republic of Pakistan
33018-1648369-1
TEXT;
        $back = <<<'TEXT'
Present Address :

House 10, PO Sample, Example Town, District
Demo Pakistan

Permanent Address :

House 10, PO Sample, Example Town, District
Demo Pakistan

The Holder is entitled visa free entry into Pakistan

I<PAKABCDEFG873520212345671<<<
8710282M3510225PAKXXXXXXXX<<<1
KHAN<<ALI<<<<<<<<<<<<<<<<<<<<<
TEXT;

        $parsed = (new CnicParserService)->parse($front, $back);

        $this->assertSame('35202-1234567-1', $parsed->cnic);
        $this->assertSame('Ali Khan', $parsed->name);
        $this->assertNull($parsed->fatherHusbandName);
        $this->assertStringContainsString('Example Town', (string) $parsed->address);
        $this->assertStringContainsString('Demo Pakistan', (string) $parsed->address);
    }

    public function test_it_prefers_mrz_cnic_over_a_garbled_front_match(): void
    {
        $text = <<<'TEXT'
Identity Number
33018-1648369-1
I<PAKABCDEFG873520212345671<<<
KHAN<<ALI<<<<<<<<<<<<<<<<<<<<<
TEXT;

        $parsed = (new CnicParserService)->parse($text);

        $this->assertSame('35202-1234567-1', $parsed->cnic);
        $this->assertSame('Ali Khan', $parsed->name);
    }

    public function test_it_keeps_front_father_name_when_mrz_has_the_holder_name(): void
    {
        $front = <<<'TEXT'
Name
Ali Khan
Father's Name
Ahmed Khan
TEXT;
        $back = <<<'TEXT'
I<PAKABCDEFG873520212345671<<<
KHAN<<ALI<<<<<<<<<<<<<<<<<<<<<
TEXT;

        $parsed = (new CnicParserService)->parse($front, $back);

        $this->assertSame('35202-1234567-1', $parsed->cnic);
        $this->assertSame('Ali Khan', $parsed->name);
        $this->assertSame('Ahmed Khan', $parsed->fatherHusbandName);
    }

    public function test_it_parses_urdu_only_card_fields(): void
    {
        $front = <<<'TEXT'
اسلامی جمہوریہ پاکستان
نام
فاطمہ خان
والد کا نام
احمد خان
شناختی نمبر
37405-1111111-2
TEXT;
        $back = <<<'TEXT'
موجودہ پتہ
خانہ نمبر ۱۲ گلی ۴ گلبگ لاہور
مستقل پتہ
خانہ نمبر ۱۲ گلی ۴ گلبگ لاہور
TEXT;

        $parsed = (new CnicParserService)->parse($front, $back);

        $this->assertSame('37405-1111111-2', $parsed->cnic);
        $this->assertSame('فاطمہ خان', $parsed->name);
        $this->assertSame('احمد خان', $parsed->fatherHusbandName);
        $this->assertStringContainsString('گلبگ لاہور', (string) $parsed->address);
    }

    public function test_it_parses_mixed_english_and_urdu_fields(): void
    {
        $front = <<<'TEXT'
Name
Ali Khan
والد کا نام
احمد خان
Identity Number
35202-1234567-1
TEXT;
        $back = <<<'TEXT'
موجودہ پتہ
خانہ نمبر ۱۲ گلی ۴ گلبگ لاہور
TEXT;

        $parsed = (new CnicParserService)->parse($front, $back);

        $this->assertSame('35202-1234567-1', $parsed->cnic);
        $this->assertSame('Ali Khan', $parsed->name);
        $this->assertSame('احمد خان', $parsed->fatherHusbandName);
        $this->assertStringContainsString('گلبگ لاہور', (string) $parsed->address);
    }

    public function test_it_prefers_mrz_name_over_short_urdu_ocr_junk(): void
    {
        $front = <<<'TEXT'
Name
پر یا
Father's Name
يا ر
TEXT;
        $back = <<<'TEXT'
I<PAKABCDEFG873520212345671<<<
KHAN<<ALI<<<<<<<<<<<<<<<<<<<<<
TEXT;

        $parsed = (new CnicParserService)->parse($front, $back);

        $this->assertSame('Ali Khan', $parsed->name);
        $this->assertNull($parsed->fatherHusbandName);
    }

    public function test_it_uses_a_second_front_latin_name_as_father(): void
    {
        $front = <<<'TEXT'
Wasim Test
Wazir Test
Identity Number
35202-1234567-1
TEXT;
        $back = <<<'TEXT'
I<PAKABCDEFG873520212345671<<<
TEST<<WASIM<<<<<<<<<<<<<<<<<<<
TEXT;

        $parsed = (new CnicParserService)->parse($front, $back);

        $this->assertSame('Wasim Test', $parsed->name);
        $this->assertSame('Wazir Test', $parsed->fatherHusbandName);
    }
}
