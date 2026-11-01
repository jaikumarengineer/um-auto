@props([
    'label' => null,
    'description' => null,
    'model' => null,
    'folder' => 'uploads',
    'allowMultiple' => false,
    'acceptedFileTypes' => ['image/*'],
    'maxFileSize' => '5MB',
    'disabled' => false,
    'preview' => null,
    'shape' => 'rectangle',
    'circle' => false,
    'maxHeight' => '9rem',
    'uploadUrl' => null,
    'revertUrl' => null,
])

@php
    $name = $model ?? $attributes->whereStartsWith('wire:model')->first() ?? $attributes->get('name');
    $invalid = $name && $errors->has($name);
    $previewStyle = $shape === 'square'
        ? "height: {$maxHeight}; width: {$maxHeight};"
        : "height: {$maxHeight}; width: auto; max-width: 16rem;";
    if ($circle) {
        $previewStyle .= ' border-radius: 9999px;';
    }

    $uploadEndpoint = $uploadUrl ?: route('admin::file-upload');
    $revertEndpoint = $revertUrl ?: route('admin::file-upload.revert');
    $previewUrl = is_string($preview) && $preview !== '' ? media_url($preview) : null;
    $folder = trim((string) $folder, '/');
@endphp

<div @class(['admin-form-control grid gap-2', 'filepond-collect' => $allowMultiple])>
    @if ($label)
        <flux:label>{{ $label }}</flux:label>
    @endif

    <div
        wire:ignore
        x-data="{
            model: @entangle($attributes->wire('model')).live,
            pond: null,
            fileId: null,
            multiple: @js((bool) $allowMultiple),
            skipRevert: 0,
            hasExisting: @js((bool) $previewUrl),
            uploads: 0,
            progress: 0,
            init() {
                window.onAdminFormsReady(() => this.setup());
            },
            setup() {
                const api = window.AdminForms;
                if (! api?.FilePond || ! this.$refs.input) return;

                const csrfToken = document.querySelector('meta[name=csrf-token]')?.content || @js(csrf_token());
                const uploadUrl = @js($uploadEndpoint);
                const revertUrl = @js($revertEndpoint);
                const folder = @js($folder);

                const parseUploadResponse = (response) => {
                    if (response == null) return { success: false, message: 'Empty response' };
                    if (typeof response === 'object') return response;
                    const text = String(response).trim();
                    if (text === '') return { success: false, message: 'Empty response' };
                    if (text.startsWith('<')) return { success: false, message: 'Session expired or server error. Please refresh.' };
                    try { return JSON.parse(text); } catch (e) { return { success: false, message: 'Invalid response' }; }
                };

                this.pond = api.FilePond.create(this.$refs.input, {
                    name: 'file',
                    credits: false,
                    instantUpload: true,
                    allowMultiple: this.multiple,
                    allowRevert: true,
                    allowImagePreview: ! this.multiple,
                    acceptedFileTypes: @js($acceptedFileTypes),
                    maxFileSize: @js($maxFileSize),
                    disabled: @js($disabled),
                    server: {
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                            'folder': folder,
                        },
                        process: {
                            url: uploadUrl,
                            method: 'POST',
                            withCredentials: true,
                            ondata: (formData) => {
                                formData.append('folder', folder);
                                return formData;
                            },
                            onload: (response) => {
                                const responseData = parseUploadResponse(response);
                                if (! responseData.success || ! responseData.data) {
                                    const message = responseData.message || 'Upload failed';
                                    alert(message);
                                    throw new Error(message);
                                }

                                if (this.multiple) {
                                    const current = Array.isArray(this.model) ? [...this.model] : [];
                                    current.push({
                                        id: responseData.data.id,
                                        url: responseData.data.url,
                                    });
                                    this.model = current;
                                } else {
                                    this.hasExisting = false;
                                    this.model = responseData.data.url;
                                    this.fileId = responseData.data.id;
                                }

                                return String(responseData.data.id ?? responseData.data.url);
                            },
                            onerror: (response) => {
                                const responseData = parseUploadResponse(response);
                                alert(responseData.message || 'Upload failed');
                            },
                        },
                        revert: (uniqueFileId, load, error) => {
                            if (this.skipRevert > 0) {
                                this.skipRevert -= 1;
                                load();
                                return;
                            }

                            const id = this.fileId || uniqueFileId;

                            fetch(revertUrl, {
                                method: 'DELETE',
                                credentials: 'same-origin',
                                headers: {
                                    'X-CSRF-TOKEN': csrfToken,
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'Accept': 'application/json',
                                    'Content-Type': 'text/plain',
                                },
                                body: String(id ?? ''),
                            })
                                .then(async (res) => {
                                    const payload = parseUploadResponse(await res.text());
                                    if (! res.ok || payload.success === false) {
                                        throw new Error(payload.message || 'Delete failed');
                                    }
                                    if (this.multiple) {
                                        const current = Array.isArray(this.model) ? this.model : [];
                                        this.model = current.filter((item) => String(item?.id ?? '') !== String(id));
                                    } else {
                                        this.model = null;
                                        this.fileId = null;
                                    }
                                    load();
                                })
                                .catch((err) => {
                                    console.error(err);
                                    error?.(err.message || 'Delete failed');
                                    load();
                                });
                        },
                    },
                });

                const finishUpload = () => {
                    this.uploads = Math.max(0, this.uploads - 1);
                    if (this.uploads === 0) {
                        this.progress = 0;
                    }
                };

                this.pond.on('processfilestart', () => {
                    this.uploads += 1;
                });
                this.pond.on('processfileprogress', (file, amount) => {
                    const value = Math.round(Math.max(0, Math.min(1, Number(amount) || 0)) * 100);
                    this.progress = value;
                });
                this.pond.on('processfileabort', finishUpload);
                this.pond.on('processfile', (error, file) => {
                    finishUpload();
                    if (! this.multiple || error || ! file) return;
                    this.skipRevert += 1;
                    this.pond.removeFile(file.id);
                });
            },
            removeExisting() {
                this.hasExisting = false;
                this.model = null;
                this.fileId = null;
                this.pond?.removeFiles();
            },
            destroy() {
                if (this.pond) {
                    window.AdminForms?.FilePond?.destroy(this.pond);
                    this.pond = null;
                }
            },
        }"
        x-on:remove="destroy()"
    >
        @if ($previewUrl)
            <div
                x-show="hasExisting"
                x-cloak
                @class([
                    'admin-filepond-preview',
                    'admin-filepond-preview-'.$shape,
                    'is-circle' => $circle,
                ])
                style="{{ $previewStyle }}"
            >
                <img src="{{ $previewUrl }}" alt="" class="admin-filepond-preview-img" />

                @unless ($disabled)
                    <button
                        type="button"
                        x-on:click="removeExisting()"
                        class="admin-filepond-remove"
                        aria-label="{{ __('Remove file') }}"
                    >
                        <flux:icon.x-mark variant="outline" class="size-3.5" />
                    </button>
                @endunless
            </div>
        @endif

        <div x-show="! hasExisting" @if ($previewUrl) x-cloak @endif>
            <input
                x-ref="input"
                type="file"
                class="filepond"
                name="file"
                @if ($disabled) disabled @endif
            />
        </div>

        <div x-show="uploads > 0" x-cloak class="mt-2">
            <div class="mb-1 flex items-center justify-between text-xs font-medium text-zinc-600 dark:text-zinc-300">
                <span>{{ __('Uploading') }}</span>
                <span x-text="`${progress}%`"></span>
            </div>
            <div class="h-1.5 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700" role="progressbar" :aria-valuenow="progress" aria-valuemin="0" aria-valuemax="100">
                <div class="h-full rounded-full bg-[var(--admin-primary)] transition-[width] duration-150" :style="`width: ${progress}%`"></div>
            </div>
        </div>
    </div>

    @if ($description)
        <flux:description>{{ $description }}</flux:description>
    @endif

    @if ($invalid)
        <flux:error :name="$name" />
    @endif

    @if ($allowMultiple)
        <style>
            .filepond-collect .filepond--root {
                min-height: 4.75rem;
            }
            .filepond-collect .filepond--drop-label {
                height: 4.75rem !important;
                min-height: 4.75rem !important;
            }
            .filepond-collect .filepond--image-preview-wrapper {
                display: none !important;
            }
            .filepond-collect .filepond--file-status {
                display: flex !important;
            }
        </style>
    @endif
</div>
