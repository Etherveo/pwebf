<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

// PRD-01/PRD-02/PRD-03: name 2-150 unique case-insensitively, category
// must exist, description optional <= 2000, price integer rupiah
// 1..100.000.000, initial stock integer 0..1.000.000 (create only),
// plus condition (baru/bekas), preorder flag + ETA, featured flag, and photo upload.
class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price_idr' => ['required', 'integer', 'min:1', 'max:100000000'],
            'condition' => ['sometimes', 'string', Rule::in(Product::CONDITIONS)],
            'is_preorder' => ['sometimes', 'boolean'],
            'preorder_eta' => ['nullable', 'string', 'max:150'],
            'is_featured' => ['sometimes', 'boolean'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];

        if ($this->isMethod('post') && $this->route('produk') === null) {
            $rules['stock_on_hand'] = ['required', 'integer', 'min:0', 'max:1000000'];
        }

        return $rules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $name = mb_strtolower((string) $this->input('name', ''));
            if ($name === '') {
                return;
            }

            $exists = Product::query()
                ->whereRaw('lower(name) = ?', [$name])
                ->when($this->route('produk') !== null, fn ($q) => $q->where('id', '!=', (int) $this->route('produk')))
                ->exists();

            if ($exists) {
                $validator->errors()->add('name', 'Nama produk sudah digunakan.');
            }
        });
    }

    public function messages(): array
    {
        $file = $this->file('image');
        $uploadError = $file instanceof UploadedFile ? $file->getError() : null;
        $uploadedMessage = in_array($uploadError, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            ? 'Ukuran foto produk melebihi batas maksimal 2 MB (batas unggah PHP: '.(ini_get('upload_max_filesize') ?: '2M').'). Pilih foto di bawah 2 MB.'
            : 'Foto produk gagal diunggah ke server (kode galat PHP: '.($uploadError ?? 'tidak diketahui').'). Pastikan ukuran foto maksimal 2 MB.';

        return [
            'image.uploaded' => $uploadedMessage,
            'image.mimes' => 'Foto produk harus berformat JPG, JPEG, PNG, atau WEBP.',
            'image.max' => 'Ukuran foto produk maksimal 2 MB.',
        ];
    }
}
