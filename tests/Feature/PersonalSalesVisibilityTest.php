<?php

namespace Tests\Feature;

use App\Models\Purchase;
use App\Models\SalesRep;
use App\Models\SalesTransaction;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PersonalSalesVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_kasir_sees_sales_when_they_are_cashier_or_sales_rep(): void
    {
        $mamat = $this->createUserWithRole('kasir', ['view sales', 'access dashboard'], 'mamat');
        $windy = $this->createUserWithRole('kasir', ['view sales'], 'windy');

        $this->createSalesRepForUser($mamat);
        $this->createSalesRepForUser($windy);

        $saleFromOwnInput = $this->createSale($mamat, $windy, 100000);
        $saleAsSalesRep = $this->createSale($windy, $mamat, 200000);
        $unrelatedSale = $this->createSale($windy, $windy, 300000);

        Sanctum::actingAs($mamat);

        $listResponse = $this->getJson('/api/sales?tipe=penjualan');
        $listResponse->assertOk();

        $visibleIds = collect($listResponse->json('data.data.data'))
            ->pluck('id')
            ->all();

        $this->assertEqualsCanonicalizing(
            [$saleFromOwnInput->id, $saleAsSalesRep->id],
            $visibleIds
        );

        $this->getJson('/api/sales/stats?tipe=penjualan')
            ->assertOk()
            ->assertJsonPath('data.total', 300000.0);

        $this->getJson("/api/sales/{$saleAsSalesRep->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $saleAsSalesRep->id);

        $this->getJson("/api/sales/{$unrelatedSale->id}")
            ->assertNotFound();

        $this->getJson('/api/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('data.cards.sales.total', 300000.0)
            ->assertJsonPath('data.is_personal_scope', true);
    }

    public function test_admin_dashboard_only_counts_their_own_transactions(): void
    {
        $admin = $this->createUserWithRole('admin', ['access dashboard'], 'admin');
        $windy = $this->createUserWithRole('kasir', [], 'windy');

        $this->createSalesRepForUser($admin);
        $this->createSalesRepForUser($windy);

        $this->createSale($admin, null, 150000);
        $this->createSale($windy, $admin, 250000);
        $this->createSale($windy, $windy, 900000);

        $supplier = Supplier::create(['nama' => 'PT Supplier Test']);
        Purchase::create([
            'no_invoice' => 'PUR-ADMIN-001',
            'tanggal' => now()->toDateString(),
            'supplier_id' => $supplier->id,
            'user_id' => $admin->id,
            'total' => 500000,
        ]);
        Purchase::create([
            'no_invoice' => 'PUR-OTHER-001',
            'tanggal' => now()->toDateString(),
            'supplier_id' => $supplier->id,
            'user_id' => $windy->id,
            'total' => 800000,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('data.is_personal_scope', true)
            ->assertJsonPath('data.cards.sales.total', 400000.0)
            ->assertJsonPath('data.cards.purchases.total', 500000.0)
            ->assertJsonPath('data.cards.summary.sales_invoices', 2)
            ->assertJsonPath('data.cards.summary.purchase_invoices', 1);
    }

    private function createUserWithRole(
        string $roleName,
        array $permissions,
        string $username
    ): User {
        $role = Role::findOrCreate($roleName, 'web');

        foreach ($permissions as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        if ($permissions !== []) {
            $role->givePermissionTo($permissions);
        }

        $user = User::factory()->create([
            'username' => $username,
            'email' => "{$username}@example.com",
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function createSalesRepForUser(User $user): void
    {
        $salesRep = new SalesRep();
        $salesRep->id = $user->id;
        $salesRep->nama = $user->name;
        $salesRep->save();
    }

    private function createSale(User $cashier, ?User $salesRep, float $grandTotal): SalesTransaction
    {
        static $sequence = 1;

        return SalesTransaction::create([
            'no_invoice' => sprintf('INVTEST%04d', $sequence++),
            'tanggal' => now()->toDateString(),
            'pelanggan' => 'Pelanggan Test',
            'user_id' => $cashier->id,
            'sales_rep_id' => $salesRep?->id,
            'subtotal' => $grandTotal,
            'diskon_persen' => 0,
            'diskon_nominal' => 0,
            'tax_persen' => 0,
            'tax_nominal' => 0,
            'grand_total' => $grandTotal,
            'metode_pembayaran' => 'cash',
            'jumlah_bayar' => $grandTotal,
            'kembalian' => 0,
            'tipe' => 'penjualan',
        ]);
    }
}
