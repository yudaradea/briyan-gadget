<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Grade;
use App\Models\Supplier;
use App\Models\Tax;
use App\Models\Unit;
use App\Models\SalesRep;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    /**
     * Seed initial master data for the POS system.
     */
    public function run(): void
    {
        // === Brands (Merk) ===
        $brands = ['Samsung', 'Apple', 'Xiaomi', 'OPPO', 'Vivo', 'Realme', 'Huawei', 'Infinix', 'Nokia'];
        foreach ($brands as $brand) {
            Brand::firstOrCreate(['nama' => $brand]);
        }
        $this->command->info('✓ ' . count($brands) . ' brands created');

        // === Categories ===
        $categories = ['Smartphone', 'Tablet', 'Laptop', 'Aksesoris', 'Sparepart', 'Charger', 'Earphone', 'Casing'];
        foreach ($categories as $category) {
            Category::firstOrCreate(['nama' => $category]);
        }
        $this->command->info('✓ ' . count($categories) . ' categories created');

        // === Grades ===
        $grades = [
            ['nama' => 'Baru/Segel', 'keterangan' => 'Barang baru masih segel resmi'],
            ['nama' => 'Like New', 'keterangan' => 'Bekas tapi kondisi seperti baru, mulus'],
            ['nama' => 'Grade A', 'keterangan' => 'Bekas, kondisi 90%+ mulus'],
            ['nama' => 'Grade B', 'keterangan' => 'Bekas, ada lecet minor'],
            ['nama' => 'Grade C', 'keterangan' => 'Bekas, ada lecet/dent yang terlihat'],
        ];
        foreach ($grades as $grade) {
            Grade::firstOrCreate(['nama' => $grade['nama']], $grade);
        }
        $this->command->info('✓ ' . count($grades) . ' grades created');

        // === Units (Satuan) ===
        $units = ['Unit', 'Pcs', 'Set', 'Pack', 'Box'];
        foreach ($units as $unit) {
            Unit::firstOrCreate(['nama' => $unit]);
        }
        $this->command->info('✓ ' . count($units) . ' units created');

        // === Sales Reps ===
        $salesReps = [
            ['nama' => 'Walk-in Customer', 'no_hp' => null],
        ];
        foreach ($salesReps as $rep) {
            SalesRep::firstOrCreate(['nama' => $rep['nama']], $rep);
        }
        $this->command->info('✓ ' . count($salesReps) . ' sales reps created');

        // === Suppliers ===
        $suppliers = [
            ['nama' => 'Supplier Umum', 'phone' => null, 'email' => null, 'alamat' => null],
        ];
        foreach ($suppliers as $supplier) {
            Supplier::firstOrCreate(['nama' => $supplier['nama']], $supplier);
        }
        $this->command->info('✓ ' . count($suppliers) . ' suppliers created');

        // === Taxes ===
        $taxes = [
            ['nama' => 'Tanpa Pajak', 'persentase' => 0, 'is_default' => true, 'is_active' => true],
            ['nama' => 'PPN 11%', 'persentase' => 11, 'is_default' => false, 'is_active' => true],
            ['nama' => 'PPN 12%', 'persentase' => 12, 'is_default' => false, 'is_active' => true],
        ];
        foreach ($taxes as $tax) {
            Tax::firstOrCreate(['nama' => $tax['nama']], $tax);
        }
        $this->command->info('✓ ' . count($taxes) . ' taxes created');
    }
}
