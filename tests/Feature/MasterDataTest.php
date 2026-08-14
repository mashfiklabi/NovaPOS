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

        // Seed granular permissions
        $permissions = [
            'categories.view', 'categories.create', 'categories.update', 'categories.delete', 'categories.restore', 'categories.bulk_delete', 'categories.bulk_restore', 'categories.export',
            'brands.view', 'brands.create', 'brands.update', 'brands.delete', 'brands.restore', 'brands.bulk_delete', 'brands.bulk_restore', 'brands.export',
            'units.view', 'units.create', 'units.update', 'units.delete', 'units.restore', 'units.bulk_delete', 'units.bulk_restore', 'units.export',
            'products.view', 'products.create', 'products.update', 'products.delete', 'products.restore', 'products.bulk_delete', 'products.bulk_restore', 'products.export',
        ];

        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // Roles
        $superAdminRole = Role::findOrCreate('Super Admin', 'web');
        $superAdminRole->syncPermissions($permissions);

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

    public function test_prevent_circular_category_loops(): void
    {
        $categoryA = Category::create([
            'name' => 'A',
            'slug' => 'a',
            'status' => 'active',
        ]);

        $categoryB = Category::create([
            'name' => 'B',
            'slug' => 'b',
            'parent_id' => $categoryA->id,
            'status' => 'active',
        ]);

        $categoryC = Category::create([
            'name' => 'C',
            'slug' => 'c',
            'parent_id' => $categoryB->id,
            'status' => 'active',
        ]);

        // Attempting to make A a child of C (A -> B -> C -> A) must be rejected
        $response = $this->actingAs($this->adminUser)->put("/categories/{$categoryA->id}", [
            'name' => 'A-Updated',
            'parent_id' => $categoryC->id,
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors(['parent_id']);
    }

    public function test_parent_scoped_category_uniqueness(): void
    {
        // Category "Accessories" under Electronics (this->category)
        Category::create([
            'name' => 'Accessories',
            'parent_id' => $this->category->id,
            'status' => 'active',
        ]);

        // Creating "Accessories" under Electronics again should fail
        $response = $this->actingAs($this->adminUser)->post('/categories', [
            'name' => 'Accessories',
            'parent_id' => $this->category->id,
            'status' => 'active',
        ]);
        $response->assertSessionHasErrors(['name']);

        // Creating "Accessories" under Root (parent_id = null) should pass
        $response = $this->actingAs($this->adminUser)->post('/categories', [
            'name' => 'Accessories',
            'parent_id' => null,
            'status' => 'active',
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('categories', ['name' => 'Accessories', 'parent_id' => null]);
    }

    // ==========================================
    // BRANDS TESTS
    // ==========================================

    public function test_authorized_user_can_create_brand_with_logo(): void
    {
        Storage::fake('local');
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
        Storage::disk('local')->assertExists($brand->logo);
    }

    public function test_authorized_user_can_securely_stream_brand_logo(): void
    {
        Storage::fake('local');
        $logoFile = UploadedFile::fake()->image('secure_brand_logo.png');
        $path = $logoFile->store('brands', 'local');

        $brand = Brand::create([
            'name' => 'Secure Brand',
            'slug' => 'secure-brand',
            'logo' => $path,
            'status' => 'active',
        ]);

        // Super Admin can stream
        $response = $this->actingAs($this->adminUser)->get("/brands/{$brand->id}/logo");
        $response->assertOk();

        // Regular user cannot stream (blocks unauthorized access to private storage)
        $response = $this->actingAs($this->regularUser)->get("/brands/{$brand->id}/logo");
        $response->assertStatus(403);
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

    public function test_product_requires_category(): void
    {
        // Category strictly required: creating product with null category must fail validation
        $response = $this->actingAs($this->adminUser)->post('/products', [
            'name' => 'No Category Product',
            'sku' => 'NO-CAT-01',
            'barcode' => '999999999',
            'category_id' => null, // null category must fail
            'brand_id' => $this->brand->id,
            'unit_id' => $this->unit->id,
            'cost_price' => 10.00,
            'selling_price' => 15.00,
            'stock_alert_threshold' => 1.000,
            'current_stock' => 5.000,
            'status' => 'active',
            'track_stock' => true,
            'allow_decimal' => false,
            'tax_type' => 'none',
            'tax_rate' => 0.00,
        ]);

        $response->assertSessionHasErrors(['category_id']);
    }

    public function test_authorized_user_can_create_and_update_products(): void
    {
        Storage::fake('local');
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
            'track_stock' => true,
            'allow_decimal' => false,
            'tax_type' => 'none',
            'tax_rate' => 0.00,
        ]);

        $response->assertRedirect();
        $product = Product::where('sku', 'MX-MASTER-01')->first();
        $this->assertNotNull($product);
        $this->assertSame('MX Master Mouse', $product->name);
        $this->assertEquals(1, $product->track_stock);
        $this->assertEquals(0, $product->allow_decimal);
        $this->assertSame('none', $product->tax_type);
        Storage::disk('local')->assertExists($product->image);
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

    public function test_authorized_user_can_permanently_force_delete_category(): void
    {
        $categoryToKill = Category::create([
            'name' => 'To Be Killed',
            'slug' => 'to-be-killed',
            'status' => 'active',
        ]);

        // Soft delete first
        $this->actingAs($this->adminUser)->delete("/categories/{$categoryToKill->id}");
        $this->assertSoftDeleted('categories', ['id' => $categoryToKill->id]);

        // Permanent force delete
        $response = $this->actingAs($this->adminUser)->delete("/categories/{$categoryToKill->id}/force-delete");
        $response->assertRedirect();
        $this->assertDatabaseMissing('categories', ['id' => $categoryToKill->id]);
    }

    public function test_authorized_user_can_bulk_delete_and_restore_categories(): void
    {
        $category2 = Category::create([
            'name' => 'Home Appliances',
            'slug' => 'home-appliances',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->adminUser)->post('/categories/bulk-delete', [
            'ids' => [$this->category->id, $category2->id]
        ]);
        $response->assertRedirect();
        $this->assertSoftDeleted('categories', ['id' => $this->category->id]);
        $this->assertSoftDeleted('categories', ['id' => $category2->id]);

        $response = $this->actingAs($this->adminUser)->post('/categories/bulk-restore', [
            'ids' => [$this->category->id, $category2->id]
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
