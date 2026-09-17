<?php

declare(strict_types=1);

namespace App\Seeds;

use App\Core\Str;

/**
 * The invitation taxonomy.
 *
 * Covers the occasions Indian - and especially Gujarati - families actually
 * celebrate. Administrators can add unlimited categories and subcategories on
 * top of this; the seeder only ever inserts what is missing.
 */
final class CategorySeeder extends Seeder
{
    /**
     * Each category: name, Gujarati, Hindi, icon, colour, and its
     * subcategories as [name, gu, hi, theme tags].
     */
    private const TREE = [
        [
            'name'  => 'Wedding',
            'gu'    => 'લગ્ન',
            'hi'    => 'विवाह',
            'icon'  => 'heart-fill',
            'color' => '#C8102E',
            'description' => 'Digital kankotri and wedding invitations in every style, from traditional Gujarati to modern minimal.',
            'children' => [
                ['Gujarati Wedding', 'ગુજરાતી લગ્ન', 'गुजराती विवाह', ['gujarati', 'traditional', 'kankotri']],
                ['Hindu Wedding', 'હિન્દુ લગ્ન', 'हिन्दू विवाह', ['hindu', 'traditional', 'vedic']],
                ['Jain Wedding', 'જૈન લગ્ન', 'जैन विवाह', ['jain', 'traditional', 'minimal']],
                ['Traditional Wedding', 'પરંપરાગત લગ્ન', 'पारंपरिक विवाह', ['traditional', 'classic']],
                ['Royal Wedding', 'રાજવી લગ્ન', 'राजसी विवाह', ['royal', 'regal', 'gold', 'maharaja']],
                ['Modern Wedding', 'મોર્ડન લગ્ન', 'मॉडर्न विवाह', ['modern', 'contemporary', 'clean']],
                ['Minimal Wedding', 'મિનિમલ લગ્ન', 'मिनिमल विवाह', ['minimal', 'simple', 'elegant']],
                ['Floral Wedding', 'ફ્લોરલ લગ્ન', 'फ्लोरल विवाह', ['floral', 'flower', 'marigold', 'rose']],
                ['Krishna Theme', 'કૃષ્ણ થીમ', 'कृष्ण थीम', ['krishna', 'flute', 'peacock', 'blue']],
                ['Radha Krishna Theme', 'રાધા કૃષ્ણ થીમ', 'राधा कृष्ण थीम', ['radha', 'krishna', 'love']],
                ['Ganesh Theme', 'ગણેશ થીમ', 'गणेश थीम', ['ganesh', 'ganpati', 'shubh']],
                ['Mahadev Theme', 'મહાદેવ થીમ', 'महादेव थीम', ['mahadev', 'shiv', 'trishul']],
                ['Ram Theme', 'રામ થીમ', 'राम थीम', ['ram', 'ayodhya', 'sita']],
                ['Swaminarayan Theme', 'સ્વામિનારાયણ થીમ', 'स्वामीनारायण थीम', ['swaminarayan', 'akshardham']],
                ['Dwarkadhish Theme', 'દ્વારકાધીશ થીમ', 'द्वारकाधीश थीम', ['dwarkadhish', 'dwarka', 'krishna']],
                ['Traditional Kankotri', 'પરંપરાગત કંકોત્રી', 'पारंपरिक कंकोत्री', ['kankotri', 'gujarati', 'traditional']],
                ['Digital Kankotri', 'ડિજિટલ કંકોત્રી', 'डिजिटल कंकोत्री', ['kankotri', 'digital', 'animated']],
                ['3D Kankotri', '3D કંકોત્રી', '3D कंकोत्री', ['3d', 'kankotri', 'envelope']],
                ['Animated Wedding', 'એનિમેટેડ લગ્ન', 'एनिमेटेड विवाह', ['animated', 'motion']],
                ['Engagement', 'સગાઈ', 'सगाई', ['engagement', 'ring', 'sagai']],
                ['Reception', 'રિસેપ્શન', 'रिसेप्शन', ['reception', 'party', 'evening']],
                ['Haldi', 'હળદી', 'हल्दी', ['haldi', 'yellow', 'turmeric']],
                ['Mehndi', 'મહેંદી', 'मेहंदी', ['mehndi', 'henna', 'green']],
                ['Sangeet', 'સંગીત', 'संगीत', ['sangeet', 'music', 'dance']],
                ['Garba', 'ગરબા', 'गरबा', ['garba', 'navratri', 'dandiya']],
                ['Wedding Ceremony', 'લગ્ન વિધિ', 'विवाह संस्कार', ['ceremony', 'vidhi', 'mandap']],
                ['Wedding Reception', 'લગ્ન રિસેપ્શન', 'विवाह रिसेप्शन', ['reception', 'dinner']],
            ],
        ],
        [
            'name'  => 'Religious & Pooja',
            'gu'    => 'ધાર્મિક અને પૂજા',
            'hi'    => 'धार्मिक और पूजा',
            'icon'  => 'brightness-high-fill',
            'color' => '#B8860B',
            'description' => 'Invitations for katha, pooja, mandir functions and pran pratishtha ceremonies.',
            'children' => [
                ['Ganesh Puja', 'ગણેશ પૂજા', 'गणेश पूजा', ['ganesh', 'pooja', 'ganpati']],
                ['Satyanarayan Katha', 'સત્યનારાયણ કથા', 'सत्यनारायण कथा', ['satyanarayan', 'katha']],
                ['Ram Katha', 'રામ કથા', 'राम कथा', ['ram', 'katha']],
                ['Bhagwat Katha', 'ભાગવત કથા', 'भागवत कथा', ['bhagwat', 'katha', 'krishna']],
                ['Mataji Function', 'માતાજી નો પ્રસંગ', 'माताजी का कार्यक्रम', ['mataji', 'devi', 'shakti']],
                ['Mandir Function', 'મંદિર પ્રસંગ', 'मंदिर कार्यक्रम', ['mandir', 'temple']],
                ['Dhwaja Ceremony', 'ધ્વજા વિધિ', 'ध्वजा समारोह', ['dhwaja', 'flag', 'temple']],
                ['Pran Pratishtha', 'પ્રાણ પ્રતિષ્ઠા', 'प्राण प्रतिष्ठा', ['pratishtha', 'temple', 'murti']],
                ['Religious Events', 'ધાર્મિક પ્રસંગ', 'धार्मिक कार्यक्रम', ['religious', 'dharmik']],
                ['Pooja Invitation', 'પૂજા આમંત્રણ', 'पूजा निमंत्रण', ['pooja', 'havan', 'yagna']],
            ],
        ],
        [
            'name'  => 'Business',
            'gu'    => 'વ્યવસાય',
            'hi'    => 'व्यापार',
            'icon'  => 'shop',
            'color' => '#0F766E',
            'description' => 'Shop openings, launches and corporate milestones with a professional finish.',
            'children' => [
                ['Shop Opening', 'દુકાન ઉદ્ઘાટન', 'दुकान उद्घाटन', ['shop', 'opening', 'muhurat']],
                ['Office Opening', 'ઓફિસ ઉદ્ઘાટન', 'ऑफिस उद्घाटन', ['office', 'opening', 'corporate']],
                ['Hotel Opening', 'હોટેલ ઉદ્ઘાટન', 'होटल उद्घाटन', ['hotel', 'opening', 'hospitality']],
                ['Restaurant Opening', 'રેસ્ટોરન્ટ ઉદ્ઘાટન', 'रेस्टोरेंट उद्घाटन', ['restaurant', 'opening', 'food']],
                ['Showroom Opening', 'શોરૂમ ઉદ્ઘાટન', 'शोरूम उद्घाटन', ['showroom', 'opening', 'retail']],
                ['Business Launch', 'બિઝનેસ લોન્ચ', 'बिज़नेस लॉन्च', ['launch', 'startup']],
                ['Product Launch', 'પ્રોડક્ટ લોન્ચ', 'प्रोडक्ट लॉन्च', ['product', 'launch']],
                ['Branch Opening', 'શાખા ઉદ્ઘાટન', 'शाखा उद्घाटन', ['branch', 'opening']],
                ['Business Anniversary', 'બિઝનેસ વર્ષગાંઠ', 'व्यापार वर्षगांठ', ['anniversary', 'milestone']],
                ['Business Event', 'બિઝનેસ પ્રસંગ', 'व्यापार कार्यक्रम', ['business', 'corporate']],
            ],
        ],
        [
            'name'  => 'Personal',
            'gu'    => 'વ્યક્તિગત',
            'hi'    => 'व्यक्तिगत',
            'icon'  => 'people-fill',
            'color' => '#7C3AED',
            'description' => 'Birthdays, baby ceremonies, griha pravesh and every family milestone.',
            'children' => [
                ['Birthday', 'જન્મદિવસ', 'जन्मदिन', ['birthday', 'cake', 'party']],
                ['Anniversary', 'વર્ષગાંઠ', 'वर्षगांठ', ['anniversary', 'couple']],
                ['Baby Shower', 'બેબી શાવર', 'बेबी शावर', ['baby', 'shower', 'godh bharai']],
                ['Naming Ceremony', 'નામકરણ', 'नामकरण', ['naming', 'namkaran', 'baby']],
                ['Mundan', 'મુંડન', 'मुंडन', ['mundan', 'baby', 'ceremony']],
                ['Thread Ceremony', 'જનોઈ', 'जनेऊ', ['janoi', 'yagnopavit', 'thread']],
                ['Griha Pravesh', 'ગૃહ પ્રવેશ', 'गृह प्रवेश', ['griha pravesh', 'housewarming', 'vastu']],
                ['Retirement', 'નિવૃત્તિ', 'सेवानिवृत्ति', ['retirement', 'farewell']],
                ['Farewell', 'વિદાય', 'विदाई', ['farewell', 'goodbye']],
                ['Reunion', 'પુનર્મિલન', 'पुनर्मिलन', ['reunion', 'friends', 'family']],
            ],
        ],
        [
            'name'  => 'Events',
            'gu'    => 'કાર્યક્રમ',
            'hi'    => 'कार्यक्रम',
            'icon'  => 'calendar-event-fill',
            'color' => '#DB2777',
            'description' => 'Cultural nights, seminars, exhibitions, Navratri and festival celebrations.',
            'children' => [
                ['Cultural Event', 'સાંસ્કૃતિક કાર્યક્રમ', 'सांस्कृतिक कार्यक्रम', ['cultural', 'program']],
                ['School Event', 'સ્કૂલ કાર્યક્રમ', 'स्कूल कार्यक्रम', ['school', 'students']],
                ['College Event', 'કોલેજ કાર્યક્રમ', 'कॉलेज कार्यक्रम', ['college', 'youth']],
                ['Corporate Event', 'કોર્પોરેટ ઇવેન્ટ', 'कॉर्पोरेट इवेंट', ['corporate', 'professional']],
                ['Seminar', 'સેમિનાર', 'सेमिनार', ['seminar', 'talk']],
                ['Conference', 'કોન્ફરન્સ', 'कॉन्फ्रेंस', ['conference', 'summit']],
                ['Exhibition', 'પ્રદર્શન', 'प्रदर्शनी', ['exhibition', 'expo']],
                ['Party', 'પાર્ટી', 'पार्टी', ['party', 'celebration']],
                ['Garba Night', 'ગરબા નાઇટ', 'गरबा नाइट', ['garba', 'navratri', 'dandiya']],
                ['Navratri', 'નવરાત્રી', 'नवरात्रि', ['navratri', 'garba', 'devi']],
                ['Festival', 'તહેવાર', 'त्योहार', ['festival', 'diwali', 'holi']],
            ],
        ],
    ];

    public function run(): void
    {
        $categoriesAdded = 0;
        $subcategoriesAdded = 0;
        $order = 0;

        foreach (self::TREE as $definition) {
            $order += 10;
            $slug = Str::slug($definition['name']);

            $categoryId = $this->db->value(
                'SELECT id FROM ' . $this->db->wrap($this->db->table('categories')) . ' WHERE slug = :slug',
                ['slug' => $slug]
            );

            if ($categoryId === null) {
                $categoryId = $this->db->insert('categories', [
                    'name'        => $definition['name'],
                    'name_gu'     => $definition['gu'],
                    'name_hi'     => $definition['hi'],
                    'slug'        => $slug,
                    'description' => $definition['description'],
                    'icon'        => $definition['icon'],
                    'color'       => $definition['color'],
                    'sort_order'  => $order,
                    'is_active'   => 1,
                    'is_featured' => 1,
                    'meta_title'  => $definition['name'] . ' invitation cards',
                    'meta_description' => $definition['description'],
                    'created_at'  => $this->now(),
                    'updated_at'  => $this->now(),
                ]);
                $categoriesAdded++;
            }

            $childOrder = 0;
            foreach ($definition['children'] as [$name, $gu, $hi, $tags]) {
                $childOrder += 10;
                $childSlug = Str::slug($name);

                $exists = (int) $this->db->value(
                    'SELECT COUNT(*) FROM ' . $this->db->wrap($this->db->table('subcategories'))
                    . ' WHERE slug = :slug',
                    ['slug' => $childSlug],
                    0
                ) > 0;
                if ($exists) {
                    continue;
                }

                $this->db->insert('subcategories', [
                    'category_id' => (int) $categoryId,
                    'name'        => $name,
                    'name_gu'     => $gu,
                    'name_hi'     => $hi,
                    'slug'        => $childSlug,
                    'description' => $name . ' invitation card designs you can customise and share in minutes.',
                    'theme_tags'  => json_encode($tags, JSON_UNESCAPED_UNICODE),
                    'sort_order'  => $childOrder,
                    'is_active'   => 1,
                    'meta_title'  => $name . ' invitation card',
                    'meta_description' => 'Free ' . $name . ' invitation card templates. Add your details, '
                        . 'customise the design and share on WhatsApp.',
                    'created_at'  => $this->now(),
                    'updated_at'  => $this->now(),
                ]);
                $subcategoriesAdded++;
            }
        }

        $this->note($categoriesAdded . ' category(ies) and ' . $subcategoriesAdded . ' subcategory(ies) added.');
    }

    /** Total subcategories the seeder defines - used by the installer UI. */
    public static function subcategoryCount(): int
    {
        $count = 0;
        foreach (self::TREE as $definition) {
            $count += count($definition['children']);
        }
        return $count;
    }
}
