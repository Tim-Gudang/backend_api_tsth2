<?php

namespace App\Services;

use App\Models\Barang;
use App\Repositories\BarangRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BarangService
{
    protected $barangRepository;
    public function __construct(BarangRepository $barangRepository)
    {
        $this->barangRepository = $barangRepository;
    }

    public function getAllBarang()
    {
        return $this->barangRepository->getAll();
    }

    public function getBarangById($id)
    {
        return $this->barangRepository->findById($id);
    }

    public function createBarang($data)
    {
        $validator = Validator::make($data, [
            'jenisbarang_id' => 'nullable|exists:jenis_barangs,id',
            'satuan_id' => 'nullable|exists:satuans,id',
            'barangcategory_id' => 'nullable|exists:barang_categories,id',
            'barang_nama' => 'required|string|max:255|unique:barangs,barang_nama',
            'barang_harga' => 'required|numeric|min:0',
            'barang_gambar' => 'nullable|string',
            'gudang_id' => 'required|exists:gudangs,id',
            'stok_tersedia' => 'required|numeric|min:0',
        ], [
            'barang_nama.unique' => 'Barang dengan nama ini sudah ada di database!',
            'barang_nama.required' => 'Nama barang wajib diisi!',
            'barang_harga.required' => 'Harga barang tidak boleh kosong!',
            'barang_harga.numeric' => 'Harga barang harus berupa angka!',
        ]);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        // Cek apakah barang dengan nama yang sama sudah dihapus (soft delete)
        $deletedBarang = Barang::onlyTrashed()->where('barang_nama', $data['barang_nama'])->first();
        if ($deletedBarang) {
            return response()->json(['message' => 'Nama barang sudah digunakan! Silakan gunakan nama lain.'], 422);
        }

        // Buat slug unik
        $slug = Str::slug($data['barang_nama']);
        $count = Barang::where('barang_slug', $slug)->count();
        if ($count > 0) {
            $slug .= '-' . ($count + 1); 
        }

        $data['barang_slug'] = $slug;
        $data['user_id'] = Auth::id();
        $data['barang_gambar'] = !empty($data['barang_gambar'])
            ? uploadBase64Image($data['barang_gambar'])
            : 'default_image.png';

        $barang = $this->barangRepository->create($data);

        $barang->gudangs()->attach($data['gudang_id'], [
            'stok_tersedia' => $data['stok_tersedia'],
            'stok_dipinjam' => 0,
            'stok_maintenance' => 0,
        ]);

        return $barang;
    }


    public function updateBarang($id, $data)
    {
        $barang = $this->barangRepository->findById($id);
        if (!$barang) {
            throw new \Exception('Barang tidak ditemukan.');
        }

        // Validasi input
        $validator = Validator::make($data, [
            'barang_nama' => 'required|string|max:255',
            'barang_harga' => 'required|numeric|min:0',
            'gudang_id' => 'required|exists:gudangs,id',
            'stok_tersedia' => 'sometimes|numeric|min:0',
        ]);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        // Cek apakah nama barang sudah digunakan oleh barang lain
        $existingBarang = Barang::where('barang_nama', $data['barang_nama'])
            ->where('id', '!=', $id)
            ->exists();

        if ($existingBarang) {
            throw new \Illuminate\Validation\ValidationException(
                Validator::make([], []), // Buat validator kosong
                response()->json(['errors' => ['barang_nama' => 'Nama barang ini sudah digunakan!']], 422)
            );
        }

        // Update slug jika nama berubah
        if ($data['barang_nama'] !== $barang->barang_nama) {
            $slug = Str::slug($data['barang_nama']);
            $count = Barang::where('barang_slug', $slug)->where('id', '!=', $id)->count();
            if ($count > 0) {
                $slug .= '-' . ($count + 1);
            }
            $data['barang_slug'] = $slug;
        }

        if (!empty($data['barang_gambar'])) {
            if ($barang->barang_gambar && $barang->barang_gambar !== 'default_image.png') {
                Storage::disk('public')->delete($barang->barang_gambar);
            }
            $data['barang_gambar'] = uploadBase64Image($data['barang_gambar']);
        }

        $this->barangRepository->update($barang, $data);

        $barang->gudangs()->syncWithoutDetaching([
            $data['gudang_id'] => [
                'stok_tersedia' => $data['stok_tersedia'] ?? 0,
                'stok_dipinjam' => 0,
                'stok_maintenance' => 0,
            ]
        ]);

        return $barang;
    }


    public function deleteBarang($id)
    {
        $barang = $this->barangRepository->findById($id);
        if (!$barang) {
            return null;
        }

        return $this->barangRepository->delete($barang);
    }


    public function findTrashedByName($name)
    {
        return Barang::onlyTrashed()->where('barang_nama', $name)->first();
    }

    public function restore($id)
    {
        $barang = Barang::onlyTrashed()->find($id);
        if ($barang) {
            $barang->restore();
        }
        return $barang;
    }
}
