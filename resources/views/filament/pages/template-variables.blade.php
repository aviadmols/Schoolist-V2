<x-filament::page>
    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h2 class="text-2xl font-bold mb-4">Template Variables & Shortcuts Guide</h2>
            <p class="text-gray-600 dark:text-gray-400 mb-6">
                Use these variables and shortcuts in your Builder Templates and AI Quick Add prompts.
            </p>
        </div>

        @php
            $variables = $this->getVariables();
            $shortcuts = $this->getShortcuts();
        @endphp

        @foreach($variables as $varName => $varData)
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-xl font-semibold mb-3 text-blue-600 dark:text-blue-400">
                    ${{ $varName }}
                </h3>
                <p class="text-gray-600 dark:text-gray-400 mb-4">{{ $varData['description'] ?? '' }}</p>

                @if(isset($varData['variables']))
                    <div class="mt-4">
                        <h4 class="font-semibold mb-2">Available Variables:</h4>
                        <div class="bg-gray-50 dark:bg-gray-900 rounded p-4 space-y-2">
                            @foreach($varData['variables'] as $var => $desc)
                                <div class="flex flex-col sm:flex-row sm:items-start gap-2">
                                    <code class="bg-gray-200 dark:bg-gray-700 px-2 py-1 rounded text-sm font-mono flex-shrink-0">{{ $var }}</code>
                                    <span class="text-gray-700 dark:text-gray-300 text-sm">{{ $desc }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if(isset($varData['sections']))
                    <div class="mt-4 space-y-4">
                        @foreach($varData['sections'] as $sectionKey => $section)
                            <div class="border-l-4 border-blue-500 pl-4">
                                <h4 class="font-semibold mb-2">{{ $section['title'] }}</h4>
                                @if(isset($section['variables']))
                                    <div class="bg-gray-50 dark:bg-gray-900 rounded p-4 space-y-2">
                                        @foreach($section['variables'] as $var => $desc)
                                            <div class="flex flex-col sm:flex-row sm:items-start gap-2">
                                                <code class="bg-gray-200 dark:bg-gray-700 px-2 py-1 rounded text-sm font-mono flex-shrink-0 break-all">{{ $var }}</code>
                                                <span class="text-gray-700 dark:text-gray-300 text-sm">{{ $desc }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                                @if(isset($section['example']))
                                    <div class="mt-2">
                                        <p class="text-sm font-semibold mb-1">Example:</p>
                                        <pre class="bg-gray-900 text-gray-100 p-3 rounded text-xs overflow-x-auto"><code>{{ $section['example'] }}</code></pre>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                @if(isset($varData['example']))
                    <div class="mt-4">
                        <h4 class="font-semibold mb-2">Example:</h4>
                        <pre class="bg-gray-900 text-gray-100 p-3 rounded text-xs overflow-x-auto"><code>{{ $varData['example'] }}</code></pre>
                    </div>
                @endif
            </div>
        @endforeach

        @foreach($shortcuts as $shortcutKey => $shortcutData)
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-xl font-semibold mb-3 text-green-600 dark:text-green-400">
                    {{ $shortcutData['title'] }}
                </h3>
                <div class="bg-gray-50 dark:bg-gray-900 rounded p-4 space-y-2">
                    @foreach($shortcutData['items'] as $shortcut => $desc)
                        <div class="flex flex-col sm:flex-row sm:items-start gap-2">
                            <code class="bg-gray-200 dark:bg-gray-700 px-2 py-1 rounded text-sm font-mono flex-shrink-0 break-all">{{ $shortcut }}</code>
                            <span class="text-gray-700 dark:text-gray-300 text-sm">{{ $desc }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-6 border border-blue-200 dark:border-blue-800">
            <h3 class="text-lg font-semibold mb-2 text-blue-800 dark:text-blue-200">💡 Tips</h3>
            <ul class="list-disc list-inside space-y-1 text-gray-700 dark:text-gray-300">
                <li>Always use <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">??</code> operator for optional values: <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">$page['weather']['text'] ?? 'No weather data'</code></li>
                <li>Use <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">@if (!empty(...))</code> to check if arrays have data before looping</li>
                <li>Popup tokens like <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">[[popup:whatsapp]]</code> will be automatically replaced with the popup HTML</li>
                <li>Data attributes enable JavaScript interactions - use them for dynamic popups and interactions</li>
            </ul>
        </div>
    </div>
</x-filament::page>
