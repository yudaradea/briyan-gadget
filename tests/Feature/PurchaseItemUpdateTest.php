<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Grade;
use App\Models\MasterProduct;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PurchaseItemUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_item_update_can_change_master_product_and_grade(): void
    {
        $this->actingAsUserWithPermissions(['edit purchases']);

        $oldBrand = Brand::create(['nama' => 'Brand Lama']);
        $newBrand = Brand::create(['nama' => 'Brand Baru']);
        $oldGrade = Grade::create(['nama' => 'Grade Lama']);
        $newGrade = Grade::create(['nama' => 'Grade Baru']);
        $unit = Unit::create(['nama' => 'Pcs']);
        $supplier = Supplier::create(['nama' => 'Supplier Test']);

        $masterProduct = MasterProduct::create([
            'nama' => 'IPHONE 11',
            'brand_id' => $oldBrand->id,
        ]);

        $replacementMasterProduct = MasterProduct::create([
            'nama' => 'IPHONE 13 PRO',
            'brand_id' => $newBrand->id,
        ]);

        $product = Product::create([
            'master_product_id' => $masterProduct->id,
            'barcode' => 'TEST-BARCODE-001',
            'grade_id' => $oldGrade->id,
            'unit_id' => $unit->id,
            'harga_modal' => 100000,
            'harga_jual' => 125000,
            'stok' => 1,
        ]);

        $purchase = Purchase::create([
            'no_invoice' => 'INV-TEST-001',
            'tanggal' => now()->toDateString(),
            'supplier_id' => $supplier->id,
            'user_id' => User::factory()->create()->id,
            'total' => 100000,
        ]);

        $item = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'qty' => 1,
            'harga_beli' => 100000,
            'subtotal' => 100000,
        ]);

        $response = $this->putJson("/api/purchases/{$purchase->id}/items/{$item->id}", [
            'master_product_id' => $replacementMasterProduct->id,
            'grade_id' => $newGrade->id,
        ]);

        $response->assertOk()->assertJsonPath('success', true);

        $product->refresh();

        $this->assertSame($replacementMasterProduct->id, $product->master_product_id);
        $this->assertSame($newGrade->id, $product->grade_id);
    }

    private function actingAsUserWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();

        foreach ($permissions as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        $user->givePermissionTo($permissions);
        Sanctum::actingAs($user);

        return $user;
    }
}
