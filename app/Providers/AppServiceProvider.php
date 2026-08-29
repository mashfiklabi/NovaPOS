<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Observers\BrandObserver;
use App\Observers\CategoryObserver;
use App\Observers\CustomerObserver;
use App\Observers\ProductObserver;
use App\Observers\PurchaseObserver;
use App\Observers\SaleObserver;
use App\Observers\SalePaymentObserver;
use App\Observers\SupplierObserver;
use App\Observers\UnitObserver;
use App\Observers\UserObserver;
use App\Policies\BrandPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\ProductPolicy;
use App\Policies\PurchasePolicy;
use App\Policies\RolePolicy;
use App\Policies\SalePolicy;
use App\Policies\SettingPolicy;
use App\Policies\SupplierPolicy;
use App\Policies\UnitPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Register model observers
        User::observe(UserObserver::class);
        Category::observe(CategoryObserver::class);
        Brand::observe(BrandObserver::class);
        Unit::observe(UnitObserver::class);
        Product::observe(ProductObserver::class);
        Supplier::observe(SupplierObserver::class);
        Purchase::observe(PurchaseObserver::class);
        Customer::observe(CustomerObserver::class);
        Sale::observe(SaleObserver::class);
        SalePayment::observe(SalePaymentObserver::class);

        // Explicitly register RBAC & configuration Policies
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Setting::class, SettingPolicy::class);

        // Register Master Data Policies
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Brand::class, BrandPolicy::class);
        Gate::policy(Unit::class, UnitPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);

        // Register Sprint 4 Policies
        Gate::policy(Supplier::class, SupplierPolicy::class);
        Gate::policy(Purchase::class, PurchasePolicy::class);

        // Register Sprint 5 Policies
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Sale::class, SalePolicy::class);

        // Define Spatie permission wildcard bypass for Super Admin role
        Gate::before(function (User $user, string $ability) {
            if ($user->hasRole('Super Admin')) {
                return true;
            }
        });
    }
}
