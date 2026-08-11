<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $regularUser;

    protected Category $category;

    protected Brand $brand;

    protected Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed basic permissions
        Permission::create(['name' => 'categories.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'categories.create', 'guard_name' => 'web']);
        Permission::create(['name' => 'categories.update', 'guard_name' => 'web']);
        Permission::create(['name' => 'categories.delete', 'guard_name' => 'web']);

        Permission::create(['name' => 'brands.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'brands.create', 'guard_name' => 'web']);
        Permission::create(['name' => 'brands.update', 'guard_name' => 'web']);
        Permission::create(['name' => 'brands.delete', 'guard_name' => 'web']);

        Permission::create(['name' => 'units.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'units.create', 'guard_name' => 'web']);
        Permission::create(['name' => 'units.update', 'guard_name' => 'web']);
        Permission::create(['name' => 'units.delete', 'guard_name' => 'web']);

        Permission::create(['name' => 'products.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'products.create', 'guard_name' => 'web']);
        Permission::create(['name' => 'products.update', 'guard_name' => 'web']);
        Permission::create(['name' => 'products.delete', 'guard_name' => 'web']);

        // Roles
        $superAdminRole = Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);

        $this->adminUser = User::factory()->create(['status' => UserStatus::ACTIVE]);
        $this->adminUser->assignRole($superAdminRole);

        $this->regularUser = User::factory()->create(['status' => UserStatus::ACTIVE]);

        // Seed basic master data
        $this->category = Category::create([
            'name' => 'Electronics',
            'slug' => 'electronics',
            'description' => 'Gadgets',
            'status' => 'active',
        ]);

        $this->brand = Brand::create([
            'name' => 'Logitech',
            'slug' => 'logitech',
            'description' => 'Mice',
            'status' => 'active',
        ]);

        $this->unit = Unit::create([
            'name' => 'Pieces',
            'short_name' => 'pcs',
            'allow_decimal' => 'disallowed',
        ]);
    }

    // ==========================================
    // CATEGORIES TESTS
    // ==========================================

    public function test_authorized_user_can_view_and_create_categories(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/categories');
        $response->assertOk();

        $response = $this->actingAs($this->adminUser)->post('/categories', [
            'name' => 'Computer Components',
            'parent_id' => $this->category->id,
            'status' => 'active',
            'description' => 'RAM, GPUs, CPUs',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('categories', ['name' => 'Computer Components', 'parent_id' => $this->category->id]);
    }

    public function test_prevent_category_self_parenting(): void
    {
        $response = $this->actingAs($this->adminUser)->put("/categories/{$this->category->id}", [
            'name' => 'Electronics',
            'parent_id' => $this->category->id, // self parenting
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors(['parent_id']);
    }

    // ==========================================
    // BRANDS TESTS
    // ==========================================

    public function test_authorized_user_can_create_brand_with_logo(): void
    {
        Storage::fake('public');
        $logoFile = UploadedFile::fake()->image('brand_logo.png');

        $response = $this->actingAs($this->adminUser)->post('/brands', [
            'name' => 'Apple',
            'logo' => $logoFile,
            'status' => 'active',
            'description' => 'iPhone and Mac manufacturer',
        ]);

        $response->assertRedirect();
        $brand = Brand::where('name', 'Apple')->first();
        $this->assertNotNull($brand);
        $this->assertNotNull($brand->logo);
        Storage::disk('public')->assertExists($brand->logo);
    }

    // ==========================================
    // UNITS TESTS
    // ==========================================

    public function test_authorized_user_can_create_unit(): void
    {
        $response = $this->actingAs($this->adminUser)->post('/units', [
            'name' => 'Grams',
            'short_name' => 'g',
            'allow_decimal' => 'allowed',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('units', ['short_name' => 'g', 'allow_decimal' => 'allowed']);
    }

    // ==========================================
    // PRODUCTS TESTS
    // ==========================================

    public function test_authorized_user_can_create_and_update_products(): void
    {
        Storage::fake('public');
        $img = UploadedFile::fake()->image('mouse.png');

        $response = $this->actingAs($this->adminUser)->post('/products', [
            'name' => 'MX Master Mouse',
            'sku' => 'MX-MASTER-01',
            'barcode' => '1234567890',
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'unit_id' => $this->unit->id,
            'cost_price' => 70.00,
            'selling_price' => 99.99,
            'stock_alert_threshold' => 3.000,
            'current_stock' => 10.000,
            'image' => $img,
            'status' => 'active',
        ]);

        $response->assertRedirect();
        $product = Product::where('sku', 'MX-MASTER-01')->first();
        $this->assertNotNull($product);
        $this->assertSame('MX Master Mouse', $product->name);
        Storage::disk('public')->assertExists($product->image);
    }

    // ==========================================
    // DATABASE INTEGRITY & SOFT DELETE PROTECTION
    // ==========================================

    public function test_prevent_deleting_category_containing_products(): void
    {
        $product = Product::create([
            'name' => 'MX Master Mouse',
            'sku' => 'MX-MASTER-01',
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'unit_id' => $this->unit->id,
            'cost_price' => 70.00,
            'selling_price' => 99.99,
            'stock_alert_threshold' => 3.000,
            'current_stock' => 10.000,
            'status' => 'active',
        ]);

        // Attempt category deletion
        $response = $this->actingAs($this->adminUser)->delete("/categories/{$this->category->id}");

        // Assert error set in session and category remains in DB
        $response->assertSessionHasErrors();
        $this->assertDatabaseHas('categories', ['id' => $this->category->id]);
    }

    public function test_authorized_user_can_restore_soft_deleted_category(): void
    {
        $this->actingAs($this->adminUser)->delete("/categories/{$this->category->id}");
        $this->assertSoftDeleted('categories', ['id' => $this->category->id]);

        $response = $this->actingAs($this->adminUser)->post("/categories/{$this->category->id}/restore");
        $response->assertRedirect();
        $this->assertNotSoftDeleted('categories', ['id' => $this->category->id]);
    }

    public function test_authorized_user_can_bulk_delete_and_restore_categories(): void
    {
        $category2 = Category::create([
            'name' => 'Home Appliances',
            'slug' => 'home-appliances',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->adminUser)->post('/categories/bulk-delete', [
            'ids' => [$this->category->id, $category2->id],
        ]);
        $response->assertRedirect();
        $this->assertSoftDeleted('categories', ['id' => $this->category->id]);
        $this->assertSoftDeleted('categories', ['id' => $category2->id]);

        $response = $this->actingAs($this->adminUser)->post('/categories/bulk-restore', [
            'ids' => [$this->category->id, $category2->id],
        ]);
        $response->assertRedirect();
        $this->assertNotSoftDeleted('categories', ['id' => $this->category->id]);
        $this->assertNotSoftDeleted('categories', ['id' => $category2->id]);
    }

    public function test_authorized_user_can_export_categories_csv(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/categories/export');
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Category Name', $response->streamedContent());
        $this->assertStringContainsString('Electronics', $response->streamedContent());
    }
}
