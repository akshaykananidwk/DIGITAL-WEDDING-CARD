<?php

declare(strict_types=1);

namespace App\Seeds;

/**
 * Reusable field sets for templates.
 *
 * This is what makes the builder form generate itself: a template points at a
 * preset, the preset becomes rows in `template_fields`, and the builder
 * renders and validates whatever those rows say. An administrator can then
 * add, remove or reorder fields per template without touching code.
 *
 * Each field: key, label, gu, hi, type, section, required, placeholder, help
 */
final class FieldPresets
{
    /** Sections in the order the builder shows them. */
    public const SECTIONS = [
        'main'     => 'Main details',
        'people'   => 'Families',
        'schedule' => 'Programme',
        'venue'    => 'Venue',
        'message'  => 'Message',
        'contact'  => 'Contact & RSVP',
        'media'    => 'Photos & music',
    ];

    public static function names(): array
    {
        return [
            'wedding'      => 'Wedding / Kankotri',
            'engagement'   => 'Engagement',
            'reception'    => 'Reception',
            'pooja'        => 'Pooja / Katha',
            'business'     => 'Business opening',
            'birthday'     => 'Birthday',
            'anniversary'  => 'Anniversary',
            'baby'         => 'Baby ceremony',
            'housewarming' => 'Griha Pravesh',
            'event'        => 'Cultural event',
            'garba'        => 'Garba / Navratri',
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function get(string $preset): array
    {
        $fields = match ($preset) {
            'wedding'      => self::wedding(),
            'engagement'   => self::engagement(),
            'reception'    => self::reception(),
            'pooja'        => self::pooja(),
            'business'     => self::business(),
            'birthday'     => self::birthday(),
            'anniversary'  => self::anniversary(),
            'baby'         => self::baby(),
            'housewarming' => self::housewarming(),
            'garba'        => self::garba(),
            default        => self::event(),
        };

        // Every preset gets the shared media, message and contact fields.
        return array_merge($fields, self::shared());
    }

    /** @return array<int,array<string,mixed>> */
    private static function field(
        string $key,
        string $label,
        string $gu,
        string $hi,
        string $type = 'text',
        string $section = 'main',
        bool $required = false,
        string $placeholder = '',
        string $help = '',
        bool $aiGeneratable = false,
        ?int $maxLength = null
    ): array {
        return [
            'field_key'         => $key,
            'label'             => $label,
            'label_gu'          => $gu,
            'label_hi'          => $hi,
            'type'              => $type,
            'section'           => $section,
            'is_required'       => $required,
            'placeholder'       => $placeholder,
            'help_text'         => $help,
            'is_ai_generatable' => $aiGeneratable,
            'max_length'        => $maxLength,
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private static function wedding(): array
    {
        return [
            self::field('invocation', 'Opening blessing', 'શુભ પંક્તિ', 'शुभ पंक्ति', 'text', 'main', false,
                '॥ શુભ લગ્ન ॥', 'A short line at the very top of the card.', true, 80),
            self::field('groom_name', 'Groom name', 'વરનું નામ', 'वर का नाम', 'text', 'main', true,
                'Rahul', '', false, 80),
            self::field('bride_name', 'Bride name', 'કન્યાનું નામ', 'वधू का नाम', 'text', 'main', true,
                'Priya', '', false, 80),
            self::field('groom_parents', 'Groom\'s parents', 'વરના માતા-પિતા', 'वर के माता-पिता', 'textarea', 'people', false,
                'Shri & Smt. Mahesh Patel', 'Shown under the groom\'s name.', false, 200),
            self::field('bride_parents', 'Bride\'s parents', 'કન્યાના માતા-પિતા', 'वधू के माता-पिता', 'textarea', 'people', false,
                'Shri & Smt. Kiran Shah', 'Shown under the bride\'s name.', false, 200),
            self::field('groom_grandparents', 'Groom\'s grandparents', 'વરના દાદા-દાદી', 'वर के दादा-दादी', 'text', 'people', false,
                '', 'Optional - traditional kankotris often include this.', false, 200),
            self::field('family_names', 'Family members', 'પરિવારજનો', 'परिवारजन', 'textarea', 'people', false,
                "Patel family\nShah family", 'One name per line. Shown as the "with best wishes" list.', false, 1500),
            self::field('wedding_date', 'Wedding date', 'લગ્ન તારીખ', 'विवाह तिथि', 'date', 'schedule', true),
            self::field('wedding_time', 'Wedding time', 'લગ્ન સમય', 'विवाह समय', 'time', 'schedule', false),
            self::field('haldi_date', 'Haldi date', 'હળદી તારીખ', 'हल्दी तिथि', 'date', 'schedule'),
            self::field('haldi_time', 'Haldi time', 'હળદી સમય', 'हल्दी समय', 'time', 'schedule'),
            self::field('mehndi_date', 'Mehndi date', 'મહેંદી તારીખ', 'मेहंदी तिथि', 'date', 'schedule'),
            self::field('mehndi_time', 'Mehndi time', 'મહેંદી સમય', 'मेहंदी समय', 'time', 'schedule'),
            self::field('sangeet_date', 'Sangeet date', 'સંગીત તારીખ', 'संगीत तिथि', 'date', 'schedule'),
            self::field('sangeet_time', 'Sangeet time', 'સંગીત સમય', 'संगीत समय', 'time', 'schedule'),
            self::field('garba_date', 'Garba date', 'ગરબા તારીખ', 'गरबा तिथि', 'date', 'schedule'),
            self::field('garba_time', 'Garba time', 'ગરબા સમય', 'गरबा समय', 'time', 'schedule'),
            self::field('reception_date', 'Reception date', 'રિસેપ્શન તારીખ', 'रिसेप्शन तिथि', 'date', 'schedule'),
            self::field('reception_time', 'Reception time', 'રિસેપ્શન સમય', 'रिसेप्शन समय', 'time', 'schedule'),
            self::field('venue', 'Venue name', 'સ્થળનું નામ', 'स्थान का नाम', 'text', 'venue', true,
                'Shreeji Party Plot', '', false, 150),
            self::field('venue_address', 'Venue address', 'સ્થળનું સરનામું', 'स्थान का पता', 'textarea', 'venue', false,
                'Near Dwarkadhish Temple, Dwarka, Gujarat', '', false, 400),
            self::field('maps_url', 'Google Maps link', 'ગૂગલ મેપ લિંક', 'गूगल मैप लिंक', 'location', 'venue', false,
                'https://maps.app.goo.gl/...', 'Paste the share link from Google Maps. Guests get a Directions button.'),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private static function engagement(): array
    {
        return [
            self::field('invocation', 'Opening blessing', 'શુભ પંક્તિ', 'शुभ पंक्ति', 'text', 'main', false,
                '॥ શુભ સગાઈ ॥', '', true, 80),
            self::field('groom_name', 'Groom name', 'વરનું નામ', 'वर का नाम', 'text', 'main', true, 'Rahul', '', false, 80),
            self::field('bride_name', 'Bride name', 'કન્યાનું નામ', 'वधू का नाम', 'text', 'main', true, 'Priya', '', false, 80),
            self::field('groom_parents', 'Groom\'s parents', 'વરના માતા-પિતા', 'वर के माता-पिता', 'text', 'people', false, '', '', false, 200),
            self::field('bride_parents', 'Bride\'s parents', 'કન્યાના માતા-પિતા', 'वधू के माता-पिता', 'text', 'people', false, '', '', false, 200),
            self::field('family_names', 'Family members', 'પરિવારજનો', 'परिवारजन', 'textarea', 'people', false, '', 'One name per line.', false, 1500),
            self::field('event_date', 'Engagement date', 'સગાઈ તારીખ', 'सगाई तिथि', 'date', 'schedule', true),
            self::field('event_time', 'Engagement time', 'સગાઈ સમય', 'सगाई समय', 'time', 'schedule'),
            self::field('venue', 'Venue name', 'સ્થળનું નામ', 'स्थान का नाम', 'text', 'venue', true, '', '', false, 150),
            self::field('venue_address', 'Venue address', 'સ્થળનું સરનામું', 'स्थान का पता', 'textarea', 'venue', false, '', '', false, 400),
            self::field('maps_url', 'Google Maps link', 'ગૂગલ મેપ લિંક', 'गूगल मैप लिंक', 'location', 'venue'),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private static function reception(): array
    {
        return [
            self::field('groom_name', 'Groom name', 'વરનું નામ', 'वर का नाम', 'text', 'main', true, '', '', false, 80),
            self::field('bride_name', 'Bride name', 'કન્યાનું નામ', 'वधू का नाम', 'text', 'main', true, '', '', false, 80),
            self::field('hosts', 'Hosted by', 'આયોજક', 'आयोजक', 'text', 'people', false, '', '', false, 200),
            self::field('family_names', 'Family members', 'પરિવારજનો', 'परिवारजन', 'textarea', 'people', false, '', '', false, 1500),
            self::field('reception_date', 'Reception date', 'રિસેપ્શન તારીખ', 'रिसेप्शन तिथि', 'date', 'schedule', true),
            self::field('reception_time', 'Reception time', 'રિસેપ્શન સમય', 'रिसेप्शन समय', 'time', 'schedule'),
            self::field('venue', 'Venue name', 'સ્થળનું નામ', 'स्थान का नाम', 'text', 'venue', true, '', '', false, 150),
            self::field('venue_address', 'Venue address', 'સ્થળનું સરનામું', 'स्थान का पता', 'textarea', 'venue', false, '', '', false, 400),
            self::field('maps_url', 'Google Maps link', 'ગૂગલ મેપ લિંક', 'गूगल मैप लिंक', 'location', 'venue'),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private static function pooja(): array
    {
        return [
            self::field('invocation', 'Opening blessing', 'શુભ પંક્તિ', 'शुभ पंक्ति', 'text', 'main', false,
                '॥ શ્રી ગણેશાય નમઃ ॥', '', true, 80),
            self::field('event_name', 'Ceremony name', 'પ્રસંગનું નામ', 'कार्यक्रम का नाम', 'text', 'main', true,
                'Satyanarayan Katha', '', false, 150),
            self::field('deity_name', 'Deity', 'દેવતા', 'देवता', 'text', 'main', false, 'Shri Satyanarayan', '', false, 100),
            self::field('host_name', 'Hosted by', 'આયોજક પરિવાર', 'आयोजक परिवार', 'text', 'people', true,
                'Patel family', '', false, 200),
            self::field('priest_name', 'Priest / Katha vachak', 'કથાકાર', 'कथावाचक', 'text', 'people', false, '', '', false, 150),
            self::field('family_names', 'Family members', 'પરિવારજનો', 'परिवारजन', 'textarea', 'people', false, '', '', false, 1500),
            self::field('event_date', 'Date', 'તારીખ', 'तिथि', 'date', 'schedule', true),
            self::field('event_time', 'Time', 'સમય', 'समय', 'time', 'schedule'),
            self::field('prasad_time', 'Prasad / Bhojan time', 'પ્રસાદ સમય', 'प्रसाद समय', 'time', 'schedule'),
            self::field('venue', 'Venue name', 'સ્થળનું નામ', 'स्थान का नाम', 'text', 'venue', true, '', '', false, 150),
            self::field('venue_address', 'Venue address', 'સ્થળનું સરનામું', 'स्थान का पता', 'textarea', 'venue', false, '', '', false, 400),
            self::field('maps_url', 'Google Maps link', 'ગૂગલ મેપ લિંક', 'गूगल मैप लिंक', 'location', 'venue'),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private static function business(): array
    {
        return [
            self::field('business_name', 'Business name', 'વ્યવસાયનું નામ', 'व्यापार का नाम', 'text', 'main', true,
                'Shree Krishna Electronics', '', false, 150),
            self::field('tagline', 'Tagline', 'ટેગલાઇન', 'टैगलाइन', 'text', 'main', false,
                'Quality you can trust', '', true, 120),
            self::field('event_name', 'Occasion', 'પ્રસંગ', 'अवसर', 'text', 'main', false,
                'Grand Opening', '', false, 100),
            self::field('proprietor', 'Proprietor', 'માલિક', 'मालिक', 'text', 'people', false, '', '', false, 150),
            self::field('chief_guest', 'Chief guest', 'મુખ્ય મહેમાન', 'मुख्य अतिथि', 'text', 'people', false, '', '', false, 200),
            self::field('opening_date', 'Opening date', 'ઉદ્ઘાટન તારીખ', 'उद्घाटन तिथि', 'date', 'schedule', true),
            self::field('opening_time', 'Opening time', 'ઉદ્ઘાટન સમય', 'उद्घाटन समय', 'time', 'schedule'),
            self::field('offer_text', 'Inauguration offer', 'ખાસ ઓફર', 'विशेष ऑफर', 'textarea', 'message', false,
                'Flat 20% off on the first day', '', true, 300),
            self::field('venue', 'Address line', 'સરનામું', 'पता', 'text', 'venue', true, '', '', false, 150),
            self::field('venue_address', 'Full address', 'પૂરું સરનામું', 'पूरा पता', 'textarea', 'venue', false, '', '', false, 400),
            self::field('maps_url', 'Google Maps link', 'ગૂગલ મેપ લિંક', 'गूगल मैप लिंक', 'location', 'venue'),
            self::field('website', 'Website', 'વેબસાઇટ', 'वेबसाइट', 'url', 'contact', false, 'https://', '', false, 200),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private static function birthday(): array
    {
        return [
            self::field('celebrant_name', 'Birthday person', 'જન્મદિવસ કોનો', 'जन्मदिन किसका', 'text', 'main', true,
                'Aarav', '', false, 80),
            self::field('age', 'Turning', 'ઉંમર', 'आयु', 'number', 'main', false, '5', 'Leave blank to hide the age.'),
            self::field('parents_names', 'Parents', 'માતા-પિતા', 'माता-पिता', 'text', 'people', false, '', '', false, 200),
            self::field('event_date', 'Party date', 'પાર્ટી તારીખ', 'पार्टी तिथि', 'date', 'schedule', true),
            self::field('event_time', 'Party time', 'પાર્ટી સમય', 'पार्टी समय', 'time', 'schedule'),
            self::field('venue', 'Venue name', 'સ્થળનું નામ', 'स्थान का नाम', 'text', 'venue', true, '', '', false, 150),
            self::field('venue_address', 'Venue address', 'સ્થળનું સરનામું', 'स्थान का पता', 'textarea', 'venue', false, '', '', false, 400),
            self::field('maps_url', 'Google Maps link', 'ગૂગલ મેપ લિંક', 'गूगल मैप लिंक', 'location', 'venue'),
            self::field('theme', 'Party theme', 'પાર્ટી થીમ', 'पार्टी थीम', 'text', 'main', false, 'Jungle safari', '', false, 80),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private static function anniversary(): array
    {
        return [
            self::field('groom_name', 'Husband name', 'પતિનું નામ', 'पति का नाम', 'text', 'main', true, '', '', false, 80),
            self::field('bride_name', 'Wife name', 'પત્નીનું નામ', 'पत्नी का नाम', 'text', 'main', true, '', '', false, 80),
            self::field('years', 'Years together', 'વર્ષ', 'वर्ष', 'number', 'main', false, '25'),
            self::field('family_names', 'Family members', 'પરિવારજનો', 'परिवारजन', 'textarea', 'people', false, '', '', false, 1500),
            self::field('event_date', 'Celebration date', 'તારીખ', 'तिथि', 'date', 'schedule', true),
            self::field('event_time', 'Celebration time', 'સમય', 'समय', 'time', 'schedule'),
            self::field('venue', 'Venue name', 'સ્થળનું નામ', 'स्थान का नाम', 'text', 'venue', true, '', '', false, 150),
            self::field('venue_address', 'Venue address', 'સ્થળનું સરનામું', 'स्थान का पता', 'textarea', 'venue', false, '', '', false, 400),
            self::field('maps_url', 'Google Maps link', 'ગૂગલ મેપ લિંક', 'गूगल मैप लिंक', 'location', 'venue'),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private static function baby(): array
    {
        return [
            self::field('event_name', 'Ceremony', 'પ્રસંગ', 'कार्यक्रम', 'select', 'main', true, '',
                'Naming, mundan, baby shower or annaprashan.'),
            self::field('baby_name', 'Baby name', 'બાળકનું નામ', 'बच्चे का नाम', 'text', 'main', false,
                '', 'Leave blank for a naming ceremony if the name is a surprise.', false, 80),
            self::field('parents_names', 'Parents', 'માતા-પિતા', 'माता-पिता', 'text', 'people', true, '', '', false, 200),
            self::field('grandparents_names', 'Grandparents', 'દાદા-દાદી', 'दादा-दादी', 'text', 'people', false, '', '', false, 200),
            self::field('event_date', 'Date', 'તારીખ', 'तिथि', 'date', 'schedule', true),
            self::field('event_time', 'Time', 'સમય', 'समय', 'time', 'schedule'),
            self::field('venue', 'Venue name', 'સ્થળનું નામ', 'स्थान का नाम', 'text', 'venue', true, '', '', false, 150),
            self::field('venue_address', 'Venue address', 'સ્થળનું સરનામું', 'स्थान का पता', 'textarea', 'venue', false, '', '', false, 400),
            self::field('maps_url', 'Google Maps link', 'ગૂગલ મેપ લિંક', 'गूगल मैप लिंक', 'location', 'venue'),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private static function housewarming(): array
    {
        return [
            self::field('invocation', 'Opening blessing', 'શુભ પંક્તિ', 'शुभ पंक्ति', 'text', 'main', false,
                '॥ વાસ્તુ શાંતિ ॥', '', true, 80),
            self::field('family_name', 'Family name', 'પરિવારનું નામ', 'परिवार का नाम', 'text', 'main', true,
                'Patel family', '', false, 150),
            self::field('house_name', 'New home', 'નવું ઘર', 'नया घर', 'text', 'main', false, 'Krishna Kunj', '', false, 120),
            self::field('family_names', 'Family members', 'પરિવારજનો', 'परिवारजन', 'textarea', 'people', false, '', '', false, 1500),
            self::field('event_date', 'Griha Pravesh date', 'ગૃહ પ્રવેશ તારીખ', 'गृह प्रवेश तिथि', 'date', 'schedule', true),
            self::field('event_time', 'Time', 'સમય', 'समय', 'time', 'schedule'),
            self::field('pooja_time', 'Vastu pooja time', 'વાસ્તુ પૂજા સમય', 'वास्तु पूजा समय', 'time', 'schedule'),
            self::field('venue', 'Address line', 'સરનામું', 'पता', 'text', 'venue', true, '', '', false, 150),
            self::field('venue_address', 'Full address', 'પૂરું સરનામું', 'पूरा पता', 'textarea', 'venue', false, '', '', false, 400),
            self::field('maps_url', 'Google Maps link', 'ગૂગલ મેપ લિંક', 'गूगल मैप लिंक', 'location', 'venue'),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private static function garba(): array
    {
        return [
            self::field('event_name', 'Event name', 'કાર્યક્રમનું નામ', 'कार्यक्रम का नाम', 'text', 'main', true,
                'Navratri Garba Mahotsav', '', false, 150),
            self::field('organiser', 'Organised by', 'આયોજક', 'आयोजक', 'text', 'people', true, '', '', false, 200),
            self::field('event_date', 'Start date', 'શરૂ તારીખ', 'प्रारंभ तिथि', 'date', 'schedule', true),
            self::field('event_time', 'Start time', 'સમય', 'समय', 'time', 'schedule'),
            self::field('end_date', 'End date', 'અંતિમ તારીખ', 'अंतिम तिथि', 'date', 'schedule'),
            self::field('artist_name', 'Singer / Artist', 'ગાયક કલાકાર', 'गायक कलाकार', 'text', 'people', false, '', '', false, 150),
            self::field('entry_details', 'Entry details', 'પ્રવેશ વિગત', 'प्रवेश विवरण', 'textarea', 'message', false,
                'Free entry in traditional dress', '', true, 300),
            self::field('venue', 'Venue name', 'સ્થળનું નામ', 'स्थान का नाम', 'text', 'venue', true, '', '', false, 150),
            self::field('venue_address', 'Venue address', 'સ્થળનું સરનામું', 'स्थान का पता', 'textarea', 'venue', false, '', '', false, 400),
            self::field('maps_url', 'Google Maps link', 'ગૂગલ મેપ લિંક', 'गूगल मैप लिंक', 'location', 'venue'),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private static function event(): array
    {
        return [
            self::field('event_name', 'Event name', 'કાર્યક્રમનું નામ', 'कार्यक्रम का नाम', 'text', 'main', true, '', '', false, 150),
            self::field('organiser', 'Organised by', 'આયોજક', 'आयोजक', 'text', 'people', true, '', '', false, 200),
            self::field('chief_guest', 'Chief guest', 'મુખ્ય મહેમાન', 'मुख्य अतिथि', 'text', 'people', false, '', '', false, 200),
            self::field('event_date', 'Date', 'તારીખ', 'तिथि', 'date', 'schedule', true),
            self::field('event_time', 'Time', 'સમય', 'समय', 'time', 'schedule'),
            self::field('venue', 'Venue name', 'સ્થળનું નામ', 'स्थान का नाम', 'text', 'venue', true, '', '', false, 150),
            self::field('venue_address', 'Venue address', 'સ્થળનું સરનામું', 'स्थान का पता', 'textarea', 'venue', false, '', '', false, 400),
            self::field('maps_url', 'Google Maps link', 'ગૂગલ મેપ લિંક', 'गूगल मैप लिंक', 'location', 'venue'),
        ];
    }

    /** Fields every template carries. */
    private static function shared(): array
    {
        return [
            self::field('custom_message', 'Invitation message', 'આમંત્રણ સંદેશ', 'निमंत्रण संदेश', 'textarea', 'message', false,
                'With the blessings of the Almighty, we warmly invite you...',
                'The main wording of your invitation. Use "Write it for me" for help.', true, 1200),
            self::field('welcome_message', 'Welcome line', 'સ્વાગત પંક્તિ', 'स्वागत पंक्ति', 'text', 'message', false,
                '', 'A single line shown right after the card opens.', true, 160),
            self::field('rsvp_name', 'RSVP contact name', 'સંપર્ક નામ', 'संपर्क नाम', 'text', 'contact', false, '', '', false, 120),
            self::field('rsvp_phone', 'RSVP phone', 'સંપર્ક નંબર', 'संपर्क नंबर', 'phone', 'contact', false, '9876543210'),
            self::field('whatsapp_number', 'WhatsApp number', 'વોટ્સએપ નંબર', 'व्हाट्सएप नंबर', 'phone', 'contact', false,
                '9876543210', 'Guests get a "Chat on WhatsApp" button.'),
            self::field('hero_photo', 'Main photo', 'મુખ્ય ફોટો', 'मुख्य फ़ोटो', 'image', 'media', false,
                '', 'Shown at the top of the invitation.'),
            self::field('gallery', 'Photo gallery', 'ફોટો ગેલેરી', 'फ़ोटो गैलरी', 'gallery', 'media', false,
                '', 'Up to 30 photos. Drag to reorder.'),
            self::field('music', 'Background music', 'પૃષ્ઠભૂમિ સંગીત', 'पृष्ठभूमि संगीत', 'music', 'media', false,
                '', 'Guests can always mute it.'),
        ];
    }

    /** Options for the select fields the presets declare. */
    public static function optionsFor(string $key): ?array
    {
        return match ($key) {
            'event_name' => null, // free text in most presets
            default      => null,
        };
    }

    /** Demo content so a brand-new invitation previews as a finished card. */
    public static function demoData(string $preset): array
    {
        $nextYear = (int) date('Y') + 1;
        $shared = [
            'custom_message' => 'With the blessings of the Almighty and the elders of our family, '
                . 'we warmly invite you to join us and bless the occasion with your presence.',
            'rsvp_name'      => 'Mahesh Patel',
            'rsvp_phone'     => '9876543210',
            'whatsapp_number' => '9876543210',
        ];

        return match ($preset) {
            'wedding' => $shared + [
                'invocation'     => '॥ Shubh Vivah ॥',
                'groom_name'     => 'Rahul',
                'bride_name'     => 'Priya',
                'groom_parents'  => 'Shri & Smt. Mahesh Patel',
                'bride_parents'  => 'Shri & Smt. Kiran Shah',
                'wedding_date'   => $nextYear . '-12-25',
                'wedding_time'   => '11:30:00',
                'mehndi_date'    => $nextYear . '-12-23',
                'mehndi_time'    => '16:00:00',
                'sangeet_date'   => $nextYear . '-12-24',
                'sangeet_time'   => '19:00:00',
                'reception_date' => $nextYear . '-12-25',
                'reception_time' => '20:00:00',
                'venue'          => 'Shreeji Party Plot',
                'venue_address'  => 'Near Dwarkadhish Temple, Dwarka, Gujarat 361335',
                'family_names'   => "Patel Family\nShah Family",
            ],
            'engagement' => $shared + [
                'groom_name'  => 'Rahul', 'bride_name' => 'Priya',
                'event_date'  => $nextYear . '-11-15', 'event_time' => '18:30:00',
                'venue'       => 'Hotel Grand Palace', 'venue_address' => 'Ring Road, Rajkot, Gujarat',
            ],
            'pooja' => $shared + [
                'invocation'  => '॥ Shri Ganeshay Namah ॥',
                'event_name'  => 'Satyanarayan Katha',
                'deity_name'  => 'Shri Satyanarayan',
                'host_name'   => 'Patel Family',
                'event_date'  => $nextYear . '-08-10', 'event_time' => '09:00:00',
                'prasad_time' => '12:30:00',
                'venue'       => 'Patel Niwas', 'venue_address' => 'Gandhi Road, Ahmedabad, Gujarat',
            ],
            'business' => $shared + [
                'business_name' => 'Shree Krishna Electronics',
                'tagline'       => 'Quality you can trust',
                'event_name'    => 'Grand Opening',
                'opening_date'  => $nextYear . '-06-05', 'opening_time' => '10:30:00',
                'offer_text'    => 'Flat 20% off on the opening day',
                'venue'         => 'Shop 12, Sardar Market',
                'venue_address' => 'Sardar Market, Surat, Gujarat 395003',
            ],
            'birthday' => $shared + [
                'celebrant_name' => 'Aarav',
                'age'            => '5',
                'parents_names'  => 'Mahesh & Nisha Patel',
                'event_date'     => $nextYear . '-03-18', 'event_time' => '17:00:00',
                'venue'          => 'Fun World Party Hall', 'venue_address' => 'CG Road, Ahmedabad, Gujarat',
                'theme'          => 'Jungle safari',
            ],
            'anniversary' => $shared + [
                'groom_name' => 'Mahesh', 'bride_name' => 'Nisha', 'years' => '25',
                'event_date' => $nextYear . '-02-14', 'event_time' => '19:00:00',
                'venue'      => 'The Grand Bhagwati', 'venue_address' => 'SG Highway, Ahmedabad, Gujarat',
            ],
            'baby' => $shared + [
                'event_name'    => 'Naming Ceremony',
                'baby_name'     => 'Vivaan',
                'parents_names' => 'Rahul & Priya Patel',
                'event_date'    => $nextYear . '-09-21', 'event_time' => '10:00:00',
                'venue'         => 'Patel Niwas', 'venue_address' => 'Satellite, Ahmedabad, Gujarat',
            ],
            'housewarming' => $shared + [
                'invocation'  => '॥ Vastu Shanti ॥',
                'family_name' => 'Patel Family',
                'house_name'  => 'Krishna Kunj',
                'event_date'  => $nextYear . '-05-12', 'event_time' => '09:30:00',
                'pooja_time'  => '10:00:00',
                'venue'       => 'B-101, Shree Residency',
                'venue_address' => 'Bopal, Ahmedabad, Gujarat 380058',
            ],
            'garba' => $shared + [
                'event_name'    => 'Navratri Garba Mahotsav',
                'organiser'     => 'Shree Yuvak Mandal',
                'event_date'    => $nextYear . '-10-03', 'event_time' => '20:00:00',
                'end_date'      => $nextYear . '-10-11',
                'artist_name'   => 'Kirtidan Gadhvi',
                'entry_details' => 'Free entry in traditional dress',
                'venue'         => 'Sardar Patel Ground', 'venue_address' => 'Vastrapur, Ahmedabad, Gujarat',
            ],
            'reception' => $shared + [
                'groom_name' => 'Rahul', 'bride_name' => 'Priya',
                'reception_date' => $nextYear . '-12-25', 'reception_time' => '20:00:00',
                'venue' => 'Shreeji Party Plot', 'venue_address' => 'Dwarka, Gujarat',
            ],
            default => $shared + [
                'event_name' => 'Annual Cultural Evening',
                'organiser'  => 'Shree Cultural Trust',
                'event_date' => $nextYear . '-07-19', 'event_time' => '18:00:00',
                'venue'      => 'Town Hall', 'venue_address' => 'Ellisbridge, Ahmedabad, Gujarat',
            ],
        };
    }
}
