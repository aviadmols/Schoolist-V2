<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class TemplateVariablesPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-code-bracket';

    protected static string $view = 'filament.pages.template-variables';

    protected static ?string $navigationGroup = 'Builder';

    protected static ?string $navigationLabel = 'Template Variables';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'template-variables';

    public static function getNavigationLabel(): string
    {
        return 'Template Variables';
    }

    public function getTitle(): string
    {
        return 'Template Variables & Shortcuts';
    }

    public function getVariables(): array
    {
        return [
            'user' => [
                'description' => 'Current logged-in user information',
                'variables' => [
                    '$user->id' => 'User ID',
                    '$user->name' => 'User full name',
                    '$user->phone' => 'User phone number',
                    '$user->email' => 'User email address',
                    '$user->role' => 'User role (site_admin, user)',
                ],
                'example' => '{{ $user->name ?? "Guest" }}',
            ],
            'classroom' => [
                'description' => 'Current classroom information',
                'variables' => [
                    '$classroom->id' => 'Classroom ID',
                    '$classroom->name' => 'Classroom name',
                    '$classroom->grade_level' => 'Grade level (א, ב, ג, etc.)',
                    '$classroom->grade_number' => 'Grade number',
                    '$classroom->school_name' => 'School name',
                    '$classroom->city_name' => 'City name',
                    '$classroom->timezone' => 'Classroom timezone',
                ],
                'example' => '{{ $classroom->name }} - {{ $classroom->grade_level }}{{ $classroom->grade_number }}',
            ],
            'page' => [
                'description' => 'Page-specific data for classroom page templates',
                'sections' => [
                    'classroom' => [
                        'title' => 'Classroom Data',
                        'variables' => [
                            '$page[\'classroom\'][\'id\']' => 'Classroom ID',
                            '$page[\'classroom\'][\'name\']' => 'Classroom name',
                            '$page[\'classroom\'][\'grade_level\']' => 'Grade level',
                            '$page[\'classroom\'][\'grade_number\']' => 'Grade number',
                            '$page[\'classroom\'][\'school_name\']' => 'School name',
                            '$page[\'classroom\'][\'city_name\']' => 'City name',
                        ],
                    ],
                    'weather' => [
                        'title' => 'Weather Information',
                        'variables' => [
                            '$page[\'weather\'][\'text\']' => 'Weather text (e.g., "22° - מעונן חלקית")',
                            '$page[\'weather\'][\'icon\']' => 'Weather emoji icon',
                            '$page[\'weather\'][\'recommendation\']' => 'Clothing recommendation',
                            '$page[\'weather\'][\'temperature\']' => 'Temperature in Celsius',
                        ],
                    ],
                    'greeting' => [
                        'title' => 'Time-based Greeting',
                        'variables' => [
                            '$page[\'greeting\']' => 'Dynamic greeting based on time (בוקר טוב, צוהריים טובים, etc.)',
                        ],
                    ],
                    'timetable' => [
                        'title' => 'Weekly Timetable',
                        'variables' => [
                            '$page[\'timetable\'][0]' => 'Sunday schedule (array of entries)',
                            '$page[\'timetable\'][1]' => 'Monday schedule',
                            '$page[\'timetable\'][2]' => 'Tuesday schedule',
                            '$page[\'timetable\'][3]' => 'Wednesday schedule',
                            '$page[\'timetable\'][4]' => 'Thursday schedule',
                            '$page[\'timetable\'][5]' => 'Friday schedule',
                            '$page[\'timetable\'][6]' => 'Saturday schedule',
                            '$page[\'timetable\'][$dayIndex][\'subject\']' => 'Subject name',
                            '$page[\'timetable\'][$dayIndex][\'start_time\']' => 'Start time (HH:MM)',
                            '$page[\'timetable\'][$dayIndex][\'end_time\']' => 'End time (HH:MM)',
                        ],
                        'example' => '@foreach ($page[\'timetable\'][0] ?? [] as $entry)
  <div>{{ $entry[\'subject\'] }} - {{ $entry[\'start_time\'] }}-{{ $entry[\'end_time\'] }}</div>
@endforeach',
                    ],
                    'announcements' => [
                        'title' => 'Announcements',
                        'variables' => [
                            '$page[\'announcements\']' => 'Array of announcements',
                            '$page[\'announcements\'][\'id\']' => 'Announcement ID',
                            '$page[\'announcements\'][\'type\']' => 'Type (message, event, homework)',
                            '$page[\'announcements\'][\'title\']' => 'Announcement title',
                            '$page[\'announcements\'][\'content\']' => 'Announcement content',
                            '$page[\'announcements\'][\'date\']' => 'Formatted date',
                            '$page[\'announcements\'][\'time\']' => 'Formatted time',
                            '$page[\'announcements\'][\'location\']' => 'Location',
                            '$page[\'announcements\'][\'is_done\']' => 'Whether user marked as done',
                            '$page[\'announcements\'][\'created_by\']' => 'Creator name',
                        ],
                        'example' => '@foreach ($page[\'announcements\'] ?? [] as $announcement)
  <div>{{ $announcement[\'title\'] }} - {{ $announcement[\'date\'] }}</div>
@endforeach',
                    ],
                    'events' => [
                        'title' => 'Events',
                        'variables' => [
                            '$page[\'events\']' => 'Array of events',
                            '$page[\'events\'][\'title\']' => 'Event title',
                            '$page[\'events\'][\'date\']' => 'Event date',
                            '$page[\'events\'][\'time\']' => 'Event time',
                            '$page[\'events\'][\'location\']' => 'Event location',
                            '$page[\'events\'][\'content\']' => 'Event description',
                            '$page[\'events\'][\'created_by\']' => 'Creator name',
                        ],
                    ],
                    'links' => [
                        'title' => 'Class Links',
                        'variables' => [
                            '$page[\'links\']' => 'Array of links',
                            '$page[\'links\'][\'title\']' => 'Link title',
                            '$page[\'links\'][\'url\']' => 'Link URL',
                            '$page[\'links\'][\'link_url\']' => 'Resolved link URL (url or file)',
                            '$page[\'links\'][\'file_url\']' => 'File URL if file',
                            '$page[\'links\'][\'category\']' => 'Category (group_whatsapp, important_links, etc.)',
                            '$page[\'links\'][\'icon\']' => 'Link icon',
                        ],
                        'example' => '@foreach ($page[\'links\'] ?? [] as $link)
  @if ($link[\'category\'] === \'group_whatsapp\')
    <a href="{{ $link[\'link_url\'] }}">{{ $link[\'title\'] }}</a>
  @endif
@endforeach',
                    ],
                    'holidays' => [
                        'title' => 'Holidays',
                        'variables' => [
                            '$page[\'holidays\']' => 'Array of upcoming holidays',
                            '$page[\'holidays\'][\'name\']' => 'Holiday name',
                            '$page[\'holidays\'][\'start_date\']' => 'Start date (formatted)',
                            '$page[\'holidays\'][\'end_date\']' => 'End date (formatted)',
                            '$page[\'holidays\'][\'has_kitan\']' => 'Whether has day camp (boolean)',
                        ],
                        'example' => '@foreach ($page[\'holidays\'] ?? [] as $holiday)
  <div>{{ $holiday[\'name\'] }} - {{ $holiday[\'start_date\'] }}
    @if ($holiday[\'has_kitan\']) 🎒 @endif
  </div>
@endforeach',
                    ],
                    'children' => [
                        'title' => 'Children',
                        'variables' => [
                            '$page[\'children\']' => 'Array of children',
                            '$page[\'children\'][\'id\']' => 'Child ID',
                            '$page[\'children\'][\'name\']' => 'Child name',
                            '$page[\'children\'][\'birth_date\']' => 'Birth date (formatted)',
                            '$page[\'children\'][\'contacts\']' => 'Array of child contacts',
                            '$page[\'children\'][\'contacts\'][\'name\']' => 'Contact name',
                            '$page[\'children\'][\'contacts\'][\'relation\']' => 'Relation (אמא, אבא, etc.)',
                            '$page[\'children\'][\'contacts\'][\'phone\']' => 'Contact phone',
                        ],
                    ],
                    'important_contacts' => [
                        'title' => 'Important Contacts',
                        'variables' => [
                            '$page[\'important_contacts\']' => 'Array of important contacts',
                            '$page[\'important_contacts\'][\'name\']' => 'Contact name',
                            '$page[\'important_contacts\'][\'role\']' => 'Contact role',
                            '$page[\'important_contacts\'][\'phone\']' => 'Contact phone',
                            '$page[\'important_contacts\'][\'email\']' => 'Contact email',
                        ],
                    ],
                    'upcoming_birthdays' => [
                        'title' => 'Upcoming Birthdays',
                        'variables' => [
                            '$page[\'upcoming_birthdays\']' => 'Array of upcoming birthdays this week',
                            '$page[\'upcoming_birthdays\'][\'name\']' => 'Child name',
                            '$page[\'upcoming_birthdays\'][\'date\']' => 'Birthday date (formatted)',
                            '$page[\'upcoming_birthdays\'][\'days_until\']' => 'Days until birthday',
                        ],
                    ],
                    'classroom_admins' => [
                        'title' => 'Classroom Administrators',
                        'variables' => [
                            '$page[\'classroom_admins\']' => 'Array of classroom admins',
                            '$page[\'classroom_admins\'][\'name\']' => 'Admin name',
                            '$page[\'classroom_admins\'][\'phone\']' => 'Admin phone',
                        ],
                    ],
                    'current_user' => [
                        'title' => 'Current User Info',
                        'variables' => [
                            '$page[\'current_user\'][\'id\']' => 'User ID',
                            '$page[\'current_user\'][\'name\']' => 'User name',
                            '$page[\'current_user\'][\'phone\']' => 'User phone',
                        ],
                    ],
                    'share_link' => [
                        'title' => 'Share Link',
                        'variables' => [
                            '$page[\'share_link\']' => 'Classroom share URL',
                        ],
                    ],
                    'selected_day' => [
                        'title' => 'Selected Day',
                        'variables' => [
                            '$page[\'selected_day\']' => 'Currently selected day index (0-6)',
                            '$page[\'day_names\']' => 'Array of day names in Hebrew',
                            '$page[\'day_labels\']' => 'Array of day labels (א, ב, ג, etc.)',
                        ],
                    ],
                ],
            ],
            'locale' => [
                'description' => 'Current locale/language',
                'variables' => [
                    '$locale' => 'Current locale code (e.g., "he", "en")',
                ],
                'example' => '{{ $locale }}',
            ],
        ];
    }

    public function getShortcuts(): array
    {
        return [
            'blade_syntax' => [
                'title' => 'Blade Syntax',
                'items' => [
                    '{{ $variable }}' => 'Output variable (escaped)',
                    '{!! $variable !!}' => 'Output variable (unescaped HTML)',
                    '@if ($condition) ... @endif' => 'Conditional statement',
                    '@foreach ($array as $item) ... @endforeach' => 'Loop through array',
                    '@empty' => 'Check if array is empty',
                    '@isset($variable)' => 'Check if variable is set',
                    '@php ... @endphp' => 'PHP code block (limited)',
                ],
            ],
            'popup_tokens' => [
                'title' => 'Popup Include Tokens',
                'items' => [
                    '[[popup:whatsapp]]' => 'Include WhatsApp popup',
                    '[[popup:holidays]]' => 'Include Holidays popup',
                    '[[popup:children]]' => 'Include Children popup',
                    '[[popup:contacts]]' => 'Include Contacts popup',
                    '[[popup:important-links]]' => 'Include Important Links popup',
                    '[[popup:links]]' => 'Include Useful Links popup',
                    '[[popup:content]]' => 'Include Content popup',
                    '[[popup:food]]' => 'Include Food popup',
                    '[[popup:schedule]]' => 'Include Schedule popup',
                ],
            ],
            'data_attributes' => [
                'title' => 'Data Attributes for JavaScript',
                'items' => [
                    'data-popup-target="popup-id"' => 'Open popup on click',
                    'data-item-popup="popup-content"' => 'Open content popup with item data',
                    'data-item-type="message"' => 'Item type (message, event, homework)',
                    'data-item-title="..."' => 'Item title',
                    'data-item-content="..."' => 'Item content',
                    'data-item-date="..."' => 'Item date',
                    'data-item-time="..."' => 'Item time',
                    'data-item-location="..."' => 'Item location',
                    'data-popup-close' => 'Close popup button',
                    'data-popup-backdrop' => 'Popup backdrop element',
                ],
            ],
        ];
    }
}
