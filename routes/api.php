<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SteamController;

Route::get('/inventory/{steamid}', [SteamController::class, 'getInventory']);
Route::middleware('throttle:item-price')->get('/item-price', [SteamController::class, 'getItemPrice']);
Route::get('/buff-price', [SteamController::class, 'getBuffPrice']);
Route::get('/inventory-search-cache/{steamid}', [SteamController::class, 'searchInventoryCache']);
Route::get('/inventory-search/{steamid}', [SteamController::class, 'searchInventory']);