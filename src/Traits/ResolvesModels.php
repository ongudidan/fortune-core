<?php

namespace Ongudidan\FortuneCore\Traits;

trait ResolvesModels
{
    public static function modelClass(string $key): string
    {
        return config("fortune.models.{$key}", 'App\\Models\\'.ucfirst($key));
    }

    public static function getUserModel(): string
    {
        return static::modelClass('user');
    }

    public static function getCustomerModel(): string
    {
        return static::modelClass('customer');
    }

    public static function getSupplierModel(): string
    {
        return static::modelClass('supplier');
    }

    public static function getCompanyModel(): string
    {
        return static::modelClass('company');
    }

    public static function getBranchModel(): string
    {
        return static::modelClass('branch');
    }

    public static function getSaleModel(): string
    {
        return static::modelClass('sale');
    }

    public static function getPurchaseModel(): string
    {
        return static::modelClass('purchase');
    }

    public static function getLocationModel(): string
    {
        return static::modelClass('location');
    }

    public static function getProductModel(): string
    {
        return static::modelClass('product');
    }
}
