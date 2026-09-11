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
@endphp

<div class="admin-form-control grid gap-2">
    @if ($label)
        <flux:label>{{ $label }}</flux:label>
    @endif

    <div
        wire:ignore
        class="admin-tiptap"
        x-data="{
            editor: null,
            debounceTimeout: null,
            active: {},
            init() {
                window.onAdminFormsReady(() => this.setup());
            },
            setup() {
                const api = window.AdminForms?.Tiptap;
                if (! api) { console.warn('Tiptap is not loaded'); return; }

                @if ($name)
                    const initial = $wire.get(@js($name)) || '';
                @else
                    const initial = '';
                @endif

                this.editor = new api.Editor({
                    element: this.$refs.content,
                    extensions: [
                        api.StarterKit.configure({ heading: { levels: [1, 2, 3] } }),
                        api.Underline,
                        api.Link.configure({ openOnClick: false, autolink: true }),
                        api.Image,
                        api.TextAlign.configure({ types: ['heading', 'paragraph'] }),
                        api.Placeholder.configure({ placeholder: @js($placeholder) }),
                        api.Table.configure({ resizable: true }),
                        api.TableRow,
                        api.TableCell,
                        api.TableHeader,
                        api.CharacterCount,
                    ],
                    editable: ! @js((bool) $disabled),
                    content: initial,
                    onUpdate: ({ editor }) => {
                        this.refresh();
                        @if ($name)
                            clearTimeout(this.debounceTimeout);
                            this.debounceTimeout = setTimeout(() => {
                                $wire.set(@js($name), editor.getHTML());
                            }, 400);
                        @endif
                    },
                    onSelectionUpdate: () => this.refresh(),
                    onTransaction: () => this.refresh(),
                });

                this.refresh();
            },
            refresh() {
                if (! this.editor) return;
                const e = this.editor;
                this.active = {
                    bold: e.isActive('bold'),
                    italic: e.isActive('italic'),
                    underline: e.isActive('underline'),
                    strike: e.isActive('strike'),
                    code: e.isActive('code'),
                    codeBlock: e.isActive('codeBlock'),
                    blockquote: e.isActive('blockquote'),
                    bulletList: e.isActive('bulletList'),
                    orderedList: e.isActive('orderedList'),
                    link: e.isActive('link'),
                    alignLeft: e.isActive({ textAlign: 'left' }),
                    alignCenter: e.isActive({ textAlign: 'center' }),
                    alignRight: e.isActive({ textAlign: 'right' }),
                    alignJustify: e.isActive({ textAlign: 'justify' }),
                    heading: e.isActive('heading', { level: 1 }) ? '1'
                        : e.isActive('heading', { level: 2 }) ? '2'
                        : e.isActive('heading', { level: 3 }) ? '3'
                        : 'p',
                };
            },
            run(fn) {
                if (! this.editor || @js((bool) $disabled)) return;
                fn(this.editor.chain().focus());
                this.refresh();
            },
            setHeading(value) {
                if (value === 'p') {
                    this.run((c) => c.setParagraph().run());
                } else {
                    this.run((c) => c.toggleHeading({ level: parseInt(value, 10) }).run());
                }
            },
            setLink() {
                if (! this.editor) return;
                const previous = this.editor.getAttributes('link').href || '';
                const url = window.prompt(@js(__('Enter URL')), previous);
                if (url === null) return;
                if (url === '') {
                    this.editor.chain().focus().extendMarkRange('link').unsetLink().run();
                    return;
                }
                this.editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
            },
            pickImage() {
                this.$refs.imageInput?.click();
            },
            async uploadImage(event) {
                const file = event.target.files?.[0];
                event.target.value = '';
                if (! file || ! this.editor) return;

                const formData = new FormData();
                formData.append('upload', file);

                try {
                    const response = await fetch(@js($uploadUrl), {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '' },
                        body: formData,
                    });
                    const result = await response.json();
                    if (result?.url) {
                        this.editor.chain().focus().setImage({ src: result.url }).run();
                    }
                } catch (e) {
                    console.error('Tiptap image upload failed', e);
                }
            },
            destroy() {
                this.editor?.destroy();
            }
        }"
        x-on:remove="destroy()"
    >
        <div class="admin-tiptap-toolbar" role="toolbar" aria-label="{{ __('Formatting') }}">
            {{-- History --}}
            <span class="admin-tiptap-group">
                <button type="button" class="admin-tiptap-btn" x-on:click="run(c => c.undo().run())" :disabled="! editor?.can().undo()" :title="@js(__('Undo'))">
                    <flux:icon.arrow-uturn-left variant="outline" class="size-4" />
                </button>
                <button type="button" class="admin-tiptap-btn" x-on:click="run(c => c.redo().run())" :disabled="! editor?.can().redo()" :title="@js(__('Redo'))">
                    <flux:icon.arrow-uturn-right variant="outline" class="size-4" />
                </button>
            </span>

            <span class="admin-tiptap-divider"></span>

            {{-- Paragraph style --}}
            <span class="admin-tiptap-group">
                <select class="admin-tiptap-select" x-on:change="setHeading($event.target.value)" x-bind:value="active.heading" :title="@js(__('Paragraph style'))">
                    <option value="p">{{ __('Paragraph') }}</option>
                    <option value="1">{{ __('Heading 1') }}</option>
                    <option value="2">{{ __('Heading 2') }}</option>
                    <option value="3">{{ __('Heading 3') }}</option>
                </select>
            </span>

            <span class="admin-tiptap-divider"></span>

            {{-- Basic styles — SVG icons to match CKEditor toolbar --}}
            <span class="admin-tiptap-group">
                <button type="button" class="admin-tiptap-btn" x-bind:class="{ 'is-active': active.bold }" x-on:click="run(c => c.toggleBold().run())" :title="@js(__('Bold'))">
                    <flux:icon.bold variant="outline" class="size-4" />
                </button>
                <button type="button" class="admin-tiptap-btn" x-bind:class="{ 'is-active': active.italic }" x-on:click="run(c => c.toggleItalic().run())" :title="@js(__('Italic'))">
                    <flux:icon.italic variant="outline" class="size-4" />
                </button>
                <button type="button" class="admin-tiptap-btn" x-bind:class="{ 'is-active': active.underline }" x-on:click="run(c => c.toggleUnderline().run())" :title="@js(__('Underline'))">
                    <flux:icon.underline variant="outline" class="size-4" />
                </button>
                <button type="button" class="admin-tiptap-btn" x-bind:class="{ 'is-active': active.strike }" x-on:click="run(c => c.toggleStrike().run())" :title="@js(__('Strikethrough'))">
                    <flux:icon.strikethrough variant="outline" class="size-4" />
                </button>
                <button type="button" class="admin-tiptap-btn" x-bind:class="{ 'is-active': active.code }" x-on:click="run(c => c.toggleCode().run())" :title="@js(__('Inline code'))">
                    <flux:icon.code-bracket variant="outline" class="size-4" />
                </button>
            </span>

            <span class="admin-tiptap-divider"></span>

            {{-- Insert --}}
            <span class="admin-tiptap-group">
                <button type="button" class="admin-tiptap-btn" x-bind:class="{ 'is-active': active.link }" x-on:click="setLink()" :title="@js(__('Link'))">
                    <flux:icon.link variant="outline" class="size-4" />
                </button>
                <button type="button" class="admin-tiptap-btn" x-on:click="pickImage()" :title="@js(__('Insert image'))">
                    <flux:icon.photo variant="outline" class="size-4" />
                </button>
                <button type="button" class="admin-tiptap-btn" x-on:click="run(c => c.insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run())" :title="@js(__('Insert table'))">
                    <flux:icon.table-cells variant="outline" class="size-4" />
                </button>
                <button type="button" class="admin-tiptap-btn" x-bind:class="{ 'is-active': active.blockquote }" x-on:click="run(c => c.toggleBlockquote().run())" :title="@js(__('Block quote'))">
                    <flux:icon.chat-bubble-bottom-center-text variant="outline" class="size-4" />
                </button>
            </span>

            <span class="admin-tiptap-divider"></span>

            {{-- Lists --}}
            <span class="admin-tiptap-group">
                <button type="button" class="admin-tiptap-btn" x-bind:class="{ 'is-active': active.bulletList }" x-on:click="run(c => c.toggleBulletList().run())" :title="@js(__('Bulleted list'))">
                    <flux:icon.list-bullet variant="outline" class="size-4" />
                </button>
                <button type="button" class="admin-tiptap-btn" x-bind:class="{ 'is-active': active.orderedList }" x-on:click="run(c => c.toggleOrderedList().run())" :title="@js(__('Numbered list'))">
                    <flux:icon.queue-list variant="outline" class="size-4" />
                </button>
            </span>

            <span class="admin-tiptap-divider"></span>

            {{-- Alignment --}}
            <span class="admin-tiptap-group">
                <button type="button" class="admin-tiptap-btn" x-bind:class="{ 'is-active': active.alignLeft }" x-on:click="run(c => c.setTextAlign('left').run())" :title="@js(__('Align left'))">
                    <flux:icon.bars-3-bottom-left variant="outline" class="size-4" />
                </button>
                <button type="button" class="admin-tiptap-btn" x-bind:class="{ 'is-active': active.alignCenter }" x-on:click="run(c => c.setTextAlign('center').run())" :title="@js(__('Align center'))">
                    <flux:icon.bars-3 variant="outline" class="size-4" />
                </button>
                <button type="button" class="admin-tiptap-btn" x-bind:class="{ 'is-active': active.alignRight }" x-on:click="run(c => c.setTextAlign('right').run())" :title="@js(__('Align right'))">
                    <flux:icon.bars-3-bottom-right variant="outline" class="size-4" />
                </button>
                <button type="button" class="admin-tiptap-btn" x-bind:class="{ 'is-active': active.alignJustify }" x-on:click="run(c => c.setTextAlign('justify').run())" :title="@js(__('Justify'))">
                    <flux:icon.bars-4 variant="outline" class="size-4" />
                </button>
            </span>

            <span class="admin-tiptap-divider"></span>

            {{-- Blocks --}}
            <span class="admin-tiptap-group">
                <button type="button" class="admin-tiptap-btn" x-bind:class="{ 'is-active': active.codeBlock }" x-on:click="run(c => c.toggleCodeBlock().run())" :title="@js(__('Code block'))">
                    <flux:icon.code-bracket-square variant="outline" class="size-4" />
                </button>
                <button type="button" class="admin-tiptap-btn" x-on:click="run(c => c.setHorizontalRule().run())" :title="@js(__('Horizontal line'))">
                    <flux:icon.minus variant="outline" class="size-4" />
                </button>
            </span>

            <input x-ref="imageInput" type="file" accept="image/*" class="hidden" x-on:change="uploadImage($event)" />
        </div>

        <div x-ref="content" class="admin-tiptap-content" style="min-height: {{ (int) $height }}px"></div>
    </div>

    @if ($description)
        <flux:description>{{ $description }}</flux:description>
    @endif

    @if ($invalid)
        <flux:error :name="$name" />
    @endif
</div>
