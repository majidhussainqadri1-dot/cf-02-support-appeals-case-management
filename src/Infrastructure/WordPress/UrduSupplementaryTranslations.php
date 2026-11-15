<?php

declare(strict_types=1);

namespace Sabri\CF02\Infrastructure\WordPress;

/**
 * Supplementary translations for rc.8 strings added after the compiled MO catalog.
 * Existing approved gettext translations always take precedence.
 *
 * Delete this supplement once the WordPress MO catalog is regenerated and reviewed.
 */
final class UrduSupplementaryTranslations
{
    private const DOMAIN = 'cf-02-support-appeals-case-management';

    /** @var array<string,string> */
    private const TRANSLATIONS = [
        'Quick public troubleshooting' => 'عوامی مسائل کے ابتدائی حل کی رہنمائی',
        'Check your connection and note the page, time, and exact error. For publishing, confirm the intended draft or published state; for PDFs, check the file type and accessibility. Never share passwords or private medical records. You can always submit a support request.' => 'اپنا انٹرنیٹ رابطہ جانچیں اور متاثرہ صفحے، وقت اور اصل خرابی کو نوٹ کریں۔ اشاعت میں مسودے کی حیثیت اور پی ڈی ایف میں فائل کی قسم اور رسائی جانچیں۔ پاس ورڈ یا نجی طبی ریکارڈ ہرگز نہ بھیجیں۔ معاونت کی درخواست ہمیشہ جمع کرائی جا سکتی ہے۔',
        'Display adjustments' => 'ظاہری ترتیب کے اختیارات',
        'Larger text' => 'بڑا متن',
        'High contrast' => 'زیادہ نمایاں رنگ',
        'Guest support pre-intake' => 'مہمان صارف کی ابتدائی درخواست',
        'Only low-sensitivity issues are accepted here. Sign in within 10 minutes, then confirm creation in the intended account. No case exists before that confirmation.' => 'یہاں صرف غیر حساس مسائل کی ابتدائی درخواست قبول ہوتی ہے۔ دس منٹ کے اندر مطلوبہ اکاؤنٹ میں داخل ہوں، پھر درخواست درج کرنے کی واضح تصدیق کریں۔ تصدیق سے پہلے کوئی کیس نہیں بنتا۔',
        'Publishing' => 'اشاعت',
        'Learning access' => 'تعلیمی رسائی',
        'Media and PDF' => 'میڈیا اور پی ڈی ایف',
        'Accessibility' => 'قابلِ رسائی استعمال',
        'Technical support' => 'تکنیکی معاونت',
        'Safe description (no sensitive information)' => 'مسئلے کی محفوظ تفصیل (حساس معلومات کے بغیر)',
        'Continue securely' => 'محفوظ طریقے سے آگے بڑھیں',
        'Sign in to complete a support request' => 'درخواست مکمل کرنے کے لیے اکاؤنٹ میں داخل ہوں',
        'Complete your pending guest request' => 'مہمان کی زیرِ التوا درخواست مکمل کریں',
        'A private support case has not been created. Before continuing, confirm this is the account that should own the request. The saved pre-intake expires after 10 minutes.' => 'ابھی نجی معاونتی کیس نہیں بنا۔ آگے بڑھنے سے پہلے تصدیق کریں کہ درخواست اسی اکاؤنٹ کے نام ہونی چاہیے۔ ابتدائی درخواست دس منٹ بعد غیر مؤثر ہو جائے گی۔',
        'Create case in this account' => 'اسی اکاؤنٹ میں کیس درج کریں',
        'Discard pending request' => 'زیرِ التوا درخواست ترک کریں',
        'Checking your safe support request…' => 'آپ کی محفوظ درخواست کی جانچ جاری ہے…',
        'This request needs an urgent specialist or official safety channel; no ordinary support case was created. Follow the emergency guidance above.' => 'یہ معاملہ فوری متعلقہ ماہر یا سرکاری حفاظتی ادارے سے رجوع کا متقاضی ہے؛ عام معاونتی کیس نہیں بنایا گیا۔ اوپر درج ہنگامی رہنمائی پر عمل کریں۔',
        'This pre-intake could not be accepted safely.' => 'ابتدائی درخواست محفوظ طریقے سے قبول نہیں کی جا سکی۔',
        'The continuation has expired. Please try again.' => 'درخواست مکمل کرنے کی مدت ختم ہو گئی۔ دوبارہ کوشش کریں۔',
        'Pre-intake accepted. No case has been created yet. Sign in using the link below in this browser tab within 10 minutes; you must explicitly confirm case creation after signing in.' => 'ابتدائی درخواست قبول ہو گئی، لیکن ابھی کیس نہیں بنایا گیا۔ اسی براؤزر ٹیب میں دس منٹ کے اندر نیچے دیے گئے ربط سے داخل ہوں، پھر کیس بنانے کی واضح تصدیق کریں۔',
        'Request failed.' => 'درخواست ناکام ہو گئی۔',

        'Pending guest request discarded; no case was created.' => 'زیرِ التوا درخواست ترک کردی گئی؛ کوئی کیس نہیں بنا۔',
        'The pending request expired; please start again.' => 'زیرِ التوا درخواست کی مدت ختم ہو گئی؛ دوبارہ آغاز کریں۔',
        'Evidence must be between 1 byte and 25 MiB.' => 'ثبوت کی فائل کا حجم ایک بائٹ سے 25 میبی بائٹ تک ہونا چاہیے۔',
    ];

    public static function translate(string $translated, string $original, string $domain): string
    {
        if ($domain !== self::DOMAIN || $translated !== $original) {
            return $translated;
        }
        $locale = (string) get_locale();
        if (!str_starts_with(strtolower($locale), 'ur')) {
            return $translated;
        }
        return self::TRANSLATIONS[$original] ?? $translated;
    }
}
