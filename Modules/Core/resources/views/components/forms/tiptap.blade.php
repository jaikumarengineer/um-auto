@props([
    'label' => null,
    'description' => null,
    'placeholder' => '',
    'height' => 260,
    'disabled' => false,
])

@php
    $name = $attributes->whereStartsWith('wire:model')->first() ?? $attributes->get('name');
    $invalid = $name && $errors->has($name);
    $uploadUrl = route('admin::editor.upload');
    $placeholder = $placeholder !== '' ? $placeholder : __('Start writing…');
@endphp

{{-- Critical mark styles live here so Vite/cache cannot hide bold/italic/etc. --}}
<style>
    .admin-tiptap .ProseMirror {
        color: #18181b;
        caret-color: #18181b;
        font-weight: 400;
        outline: none;
    }
    .dark .admin-tiptap .ProseMirror {
        color: #f4f4f5;
        caret-color: #f4f4f5;
    }
    .admin-tiptap .ProseMirror strong,
    .admin-tiptap .ProseMirror b,
    .admin-tiptap .admin-tiptap-bold {
        font-weight: 700 !important;
        font-synthesis: weight !important;
    }
    .admin-tiptap .ProseMirror em,
    .admin-tiptap .ProseMirror i,
    .admin-tiptap .admin-tiptap-italic {
        font-style: italic !important;
        font-synthesis: style !important;
    }
    .admin-tiptap .ProseMirror u,
    .admin-tiptap .admin-tiptap-underline {
        text-decoration: underline !important;
        text-underline-offset: 0.14em;
        text-decoration-thickness: from-font;
    }
    .admin-tiptap .ProseMirror s,
    .admin-tiptap .ProseMirror del,
    .admin-tiptap .admin-tiptap-strike {
        text-decoration: line-through !important;
    }
    .admin-tiptap .ProseMirror a {
        color: var(--admin-primary, #0d9488);
        text-decoration: underline;
    }
</style>

<div class="admin-form-control grid gap-2">
    @if ($label)
        <flux:label>{{ $label }}</flux:label>
    @endif

    <div
        wire:ignore
        class="admin-tiptap"
        x-data="adminTiptap({
            wireModel: @js($name),
            placeholder: @js($placeholder),
            disabled: @js((bool) $disabled),
            uploadUrl: @js($uploadUrl),
            linkPrompt: @js(__('Enter URL')),
        })"
        x-on:remove="destroy()"
    >
        <div class="admin-tiptap-toolbar" role="toolbar" aria-label="{{ __('Formatting') }}">
            <span class="admin-tiptap-group">
                <button type="button" class="admin-tiptap-btn" x-on:mousedown.prevent x-on:click="run(c => c.undo().run())" :disabled="! canUndo" :title="@js(__('Undo'))">
                    <flux:icon.arrow-uturn-left variant="outline" class="size-4" />
                </button>
                <button type="button" class="admin-tiptap-btn" x-on:mousedown.prevent x-on:click="run(c => c.redo().run())" :disabled="! canRedo" :title="@js(__('Redo'))">
                    <flux:icon.arrow-uturn-right variant="outline" class="size-4" />
                </button>
            </span>

            <span class="admin-tiptap-divider"></span>

            <span class="admin-tiptap-group">
                <select class="admin-tiptap-select" x-on:mousedown.stop x-on:change="setHeading($event.target.value)" x-bind:value="active.heading" :title="@js(__('Paragraph style'))">
                    <option value="p">{{ __('Paragraph') }}</option>
                    <option value="1">{{ __('Heading 1') }}</option>
                    <option value="2">{{ __('Heading 2') }}</option>
                    <option value="3">{{ __('Heading 3') }}</option>
                </select>
            </span>

            <span class="admin-tiptap-divider"></span>

            <span class="admin-tiptap-group">
                <button type="button" class="admin-tiptap-btn" x-on:mousedown.prevent x-bind:class="{ 'is-active': active.bold }" :aria-pressed="!!active.bold" x-on:click="run(c => c.toggleBold().run())" :title="@js(__('Bold'))">
                    <flux:icon.bold variant="outline" class="size-4" />
                </button>
                <button type="button" class="admin-tiptap-btn" x-on:mousedown.prevent x-bind:class="{ 'is-active': active.italic }" :aria-pressed="!!active.italic" x-on:click="run(c => c.toggleItalic().run())" :title="@js(__('Italic'))">
                    <flux:icon.italic variant="outline" class="size-4" />
                </button>
                <button type="button" class="admin-tiptap-btn" x-on:mousedown.prevent x-bind:class="{ 'is-active': active.underline }" :aria-pressed="!!active.underline" x-on:click="run(c => c.toggleUnderline().run())" :title="@js(__('Underline'))">
                    <flux:icon.underline variant="outline" class="size-4" />
                </button>
                <button type="button" class="admin-tiptap-btn" x-on:mousedown.prevent x-bind:class="{ 'is-active': active.strike }" :aria-pressed="!!active.strike" x-on:click="run(c => c.toggleStrike().run())" :title="@js(__('Strikethrough'))">
                    <flux:icon.strikethrough variant="outline" class="size-4" />
                </button>
                <button type="button" class="admin-tiptap-btn" x-on:mousedown.prevent x-bind:class="{ 'is-active': active.code }" :aria-pressed="!!active.code" x-on:click="run(c => c.toggleCode().run())" :title="@js(__('Inline code'))">
                    <flux:icon.code-bracket variant="outline" class="size-4" />
                </button>
            </span>

            <span class="admin-tiptap-divider"></span>

            <span class="admin-tiptap-group">
                <button type="button" class="admin-tiptap-btn" x-on:mousedown.prevent x-bind:class="{ 'is-active': active.link }" :aria-pressed="!!active.link" x-on:click="setLink()" :title="@js(__('Link'))">
                    <flux:icon.link variant="outline" class="size-4" />
                </button>
                <button type="button" class="admin-tiptap-btn" x-on:mousedown.prevent x-on:click="pickImage()" :title="@js(__('Insert image'))">
                    <flux:icon.photo variant="outline" class="size-4" />
                </button>
                <button type="button" class="admin-tiptap-btn" x-on:mousedown.prevent x-on:click="run(c => c.insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run())" :title="@js(__('Insert table'))">
                    <flux:icon.table-cells variant="outline" class="size-4" />
                </button>
                <button type="button" class="admin-tiptap-btn" x-on:mousedown.prevent x-bind:class="{ 'is-active': active.blockquote }" :aria-pressed="!!active.blockquote" x-on:click="run(c => c.toggleBlockquote().run())" :title="@js(__('Block quote'))">
                    <flux:icon.chat-bubble-bottom-center-text variant="outline" class="size-4" />
                </button>
            </span>

            <span class="admin-tiptap-divider"></span>

            <span class="admin-tiptap-group">
                <button type="button" class="admin-tiptap-btn" x-on:mousedown.prevent x-bind:class="{ 'is-active': active.bulletList }" :aria-pressed="!!active.bulletList" x-on:click="run(c => c.toggleBulletList().run())" :title="@js(__('Bulleted list'))">
                    <flux:icon.list-bullet variant="outline" class="size-4" />
                </button>
                <button type="button" class="admin-tiptap-btn" x-on:mousedown.prevent x-bind:class="{ 'is-active': active.orderedList }" :aria-pressed="!!active.orderedList" x-on:click="run(c => c.toggleOrderedList().run())" :title="@js(__('Numbered list'))">
                    <flux:icon.queue-list variant="outline" class="size-4" />
                </button>
            </span>

            <span class="admin-tiptap-divider"></span>

            <span class="admin-tiptap-group">
                <button type="button" class="admin-tiptap-btn" x-on:mousedown.prevent x-bind:class="{ 'is-active': active.alignLeft }" :aria-pressed="!!active.alignLeft" x-on:click="run(c => c.setTextAlign('left').run())" :title="@js(__('Align left'))">
                    <flux:icon.bars-3-bottom-left variant="outline" class="size-4" />
                </button>
                <button type="button" class="admin-tiptap-btn" x-on:mousedown.prevent x-bind:class="{ 'is-active': active.alignCenter }" :aria-pressed="!!active.alignCenter" x-on:click="run(c => c.setTextAlign('center').run())" :title="@js(__('Align center'))">
                    <flux:icon.bars-3 variant="outline" class="size-4" />
                </button>
                <button type="button" class="admin-tiptap-btn" x-on:mousedown.prevent x-bind:class="{ 'is-active': active.alignRight }" :aria-pressed="!!active.alignRight" x-on:click="run(c => c.setTextAlign('right').run())" :title="@js(__('Align right'))">
                    <flux:icon.bars-3-bottom-right variant="outline" class="size-4" />
                </button>
                <button type="button" class="admin-tiptap-btn" x-on:mousedown.prevent x-bind:class="{ 'is-active': active.alignJustify }" :aria-pressed="!!active.alignJustify" x-on:click="run(c => c.setTextAlign('justify').run())" :title="@js(__('Justify'))">
                    <flux:icon.bars-4 variant="outline" class="size-4" />
                </button>
            </span>

            <span class="admin-tiptap-divider"></span>

            <span class="admin-tiptap-group">
                <button type="button" class="admin-tiptap-btn" x-on:mousedown.prevent x-bind:class="{ 'is-active': active.codeBlock }" :aria-pressed="!!active.codeBlock" x-on:click="run(c => c.toggleCodeBlock().run())" :title="@js(__('Code block'))">
                    <flux:icon.code-bracket-square variant="outline" class="size-4" />
                </button>
                <button type="button" class="admin-tiptap-btn" x-on:mousedown.prevent x-on:click="run(c => c.setHorizontalRule().run())" :title="@js(__('Horizontal line'))">
                    <flux:icon.minus variant="outline" class="size-4" />
                </button>
            </span>

            <input x-ref="imageInput" type="file" accept="image/*" class="hidden" x-on:change="uploadImage($event)" />
        </div>

        <div x-ref="content" class="admin-tiptap-content" style="min-height: {{ (int) $height }}px"></div>

        <div class="admin-tiptap-status" aria-live="polite">
            <span x-text="words + ' {{ __('words') }}'"></span>
            <span x-text="chars + ' {{ __('characters') }}'"></span>
        </div>
    </div>

    @if ($description)
        <flux:description>{{ $description }}</flux:description>
    @endif

    @if ($invalid)
        <flux:error :name="$name" />
    @endif
</div>
