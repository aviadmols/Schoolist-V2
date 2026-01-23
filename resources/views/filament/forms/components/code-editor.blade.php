@php
    $id = $getId();
    $statePath = $getStatePath();
    $rows = $getRows();
@endphp

<x-filament::input.wrapper :id="$id">
  <div x-data="filamentCodeEditor()">
    <textarea
      id="{{ $id }}"
      rows="{{ $rows }}"
      wrap="off"
      spellcheck="false"
      {{ $applyStateBindingModifiers('wire:model') }}="{{ $statePath }}"
      {{ $attributes->class(['filament-code-editor']) }}
      x-on:keydown.tab.prevent="insertTab($event)"
    ></textarea>
  </div>
</x-filament::input.wrapper>

@once
  <style>
    .filament-code-editor {
      width: 100%;
      min-height: 360px;
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
      font-size: 12px;
      line-height: 1.5;
      tab-size: 2;
      padding: 12px;
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      background: #0b1021;
      color: #e5e7eb;
    }

    .filament-code-editor:focus {
      outline: 2px solid #2563eb;
      outline-offset: 2px;
    }
  </style>
@endonce

@once
  <script>
    function filamentCodeEditor() {
      return {
        insertTab(event) {
          const textarea = event.target;
          const start = textarea.selectionStart;
          const end = textarea.selectionEnd;
          const value = textarea.value;
          const tab = '  ';

          textarea.value = value.substring(0, start) + tab + value.substring(end);
          textarea.selectionStart = textarea.selectionEnd = start + tab.length;
          textarea.dispatchEvent(new Event('input'));
        },
      };
    }
  </script>
@endonce
