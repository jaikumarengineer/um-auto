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
        : "max-height: {$maxHeight}; width: 100%; max-width: 16rem;";
    if ($circle) {
        $previewStyle .= ' border-radius: 9999px;';
    }

    $uploadEndpoint = $uploadUrl ?: route('admin::file-upload');
    $revertEndpoint = $revertUrl ?: route('admin::file-upload.revert');
    $previewUrl = is_string($preview) && $preview !== '' ? media_url($preview) : null;
    $folder = trim((string) $folder, '/');
@endphp

<div class="admin-form-control grid gap-2">
    @if ($label)
        <flux:label>{{ $label }}</flux:label>
    @endif

    <div
        wire:ignore
        x-data="{
            model: @entangle($attributes->wire('model')).live,
            pond: null,
            fileId: null,
            hasExisting: @js((bool) $previewUrl),
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
                    allowMultiple: @js($allowMultiple),
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

                                this.hasExisting = false;
                                this.model = responseData.data.url;
                                this.fileId = responseData.data.id;

                                return String(responseData.data.id ?? responseData.data.url);
                            },
                            onerror: (response) => {
                                const responseData = parseUploadResponse(response);
                                alert(responseData.message || 'Upload failed');
                            },
                        },
                        revert: (uniqueFileId, load, error) => {
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
                                    this.model = null;
                                    this.fileId = null;
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
                class="admin-filepond-preview admin-filepond-preview-{{ $shape }}"
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
    </div>

    @if ($description)
        <flux:description>{{ $description }}</flux:description>
    @endif

    @if ($invalid)
        <flux:error :name="$name" />
    @endif
</div>
