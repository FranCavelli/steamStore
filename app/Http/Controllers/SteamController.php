<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SteamController extends Controller
{
    
    public function getInventory(Request $request, $steamid)
    {
        $appid = 730;
        $contextid = 2;
        $lang = 'spanish';

        $perPage = $request->query('per_page', 50);
        $startAssetId = $request->query('start_assetid');
        $cacheKey = "steam_inventory_{$steamid}_{$appid}_{$contextid}_{$lang}_{$perPage}_{$startAssetId}";

        // Traer del cache si existe
        $data = Cache::get($cacheKey);

        if (!$data) {
            $data = $this->fetchInventory($steamid, $appid, $contextid, $lang, $perPage, $startAssetId);
            Cache::put($cacheKey, $data, now()->addMinutes(5));
        } else {
            dispatch(function () use ($steamid, $appid, $contextid, $lang, $perPage, $startAssetId, $cacheKey) {
                $fresh = $this->fetchInventory($steamid, $appid, $contextid, $lang, $perPage, $startAssetId);
                Cache::put($cacheKey, $fresh, now()->addMinutes(5));
            });
        }

        if (!$data || !isset($data['descriptions'])) {
            return response()->json(['error' => 'Inventario vacío o no disponible'], 404);
        }

        $items = collect($data['descriptions'])->map(function ($item) use ($appid) {
            $marketName = $item['market_hash_name'] ?? $item['name'];

            return [
                'name'       => $item['name'] ?? 'Sin nombre',
                'marketName' => $marketName,
                'image'      => isset($item['icon_url'])
                    ? "https://steamcommunity-a.akamaihd.net/economy/image/{$item['icon_url']}"
                    : null,
                'tradable'   => $item['tradable'] ?? 0,
                'marketable' => $item['marketable'] ?? 0,
                'type'       => $item['type'] ?? null,
                'exterior'   => $item['market_hash_name'] ?? null,
                'prices'     => null,
            ];
        })->values();

        return response()->json([
            'total'        => $items->count(),
            'items'        => $items,
            'more_items'   => $data['more_items'] ?? false,
            'last_assetid' => $data['last_assetid'] ?? null,
        ]);
    }

    public function getItemPrice(Request $request)
    {
        $appid = 730;
        $marketName = $request->query('market_name');

        if (!$marketName) {
            return response()->json(['error' => 'Falta market_name'], 400);
        }

        $price = Cache::remember("steam_price_{$appid}_" . md5($marketName), 600, function () use ($appid, $marketName) {
            $priceUrl = "https://steamcommunity.com/market/priceoverview/"
                . "?currency=34&appid={$appid}&market_hash_name=" . urlencode($marketName);

            $priceRes = Http::timeout(10)->withOptions(['verify' => false])->get($priceUrl);

            if (!$priceRes->successful()) {
                \Log::warning("Steam API error: {$priceRes->status()} - {$priceRes->body()}");
                return null;
            }

            return $priceRes->json();
        });

        if (!$price) {
            return response()->json([
                'lowest_price' => null,
                'median_price' => null,
                'volume' => null,
                'message' => 'Precio no disponible',
            ]);
        }


        return response()->json($price);
    }

    private function fetchInventory($steamid, $appid, $contextid, $lang, $perPage, $startAssetId = null)
    {
        $url = "https://steamcommunity.com/inventory/{$steamid}/{$appid}/{$contextid}?l={$lang}&count={$perPage}";
        if ($startAssetId) {
            $url .= "&start_assetid={$startAssetId}";
        }

        $response = Http::withOptions(['verify' => false])->get($url);

        return $response->successful() ? $response->json() : null;
    }

    private function fetchAllInventoryAndCache($steamid, $appid, $contextid, $lang)
    {
        $allDescriptions = [];
        $perPage = 100;
        $startAssetId = null;
        do {
            $data = $this->fetchInventory($steamid, $appid, $contextid, $lang, $perPage, $startAssetId);
            if (!$data || !isset($data['descriptions'])) break;
            $allDescriptions = array_merge($allDescriptions, $data['descriptions']);
            $cacheKey = "steam_inventory_{$steamid}_{$appid}_{$contextid}_{$lang}_{$perPage}_{$startAssetId}";
            Cache::put($cacheKey, $data, now()->addMinutes(5));
            $startAssetId = $data['last_assetid'] ?? null;
            $moreItems = $data['more_items'] ?? false;
        } while ($moreItems && $startAssetId);

        return $allDescriptions;
    }

    public function searchInventoryCache(Request $request, $steamid)
    {
        $appid = 730;
        $contextid = 2;
        $lang = 'spanish';
        $query = strtolower($request->query('q', ''));
        $perPage = 100;
        $startAssetId = null;

        $allDescriptions = [];
        do {
            $cacheKey = "steam_inventory_{$steamid}_{$appid}_{$contextid}_{$lang}_{$perPage}_{$startAssetId}";
            $data = Cache::get($cacheKey);
            if (!$data || !isset($data['descriptions'])) break;
            $allDescriptions = array_merge($allDescriptions, $data['descriptions']);
            $startAssetId = $data['last_assetid'] ?? null;
            $moreItems = $data['more_items'] ?? false;
        } while ($moreItems && $startAssetId);

        $items = collect($allDescriptions)->map(function ($item) use ($appid) {
            $marketName = $item['market_hash_name'] ?? $item['name'];
            return [
                'name'       => $item['name'] ?? 'Sin nombre',
                'marketName' => $marketName,
                'image'      => isset($item['icon_url'])
                    ? "https://steamcommunity-a.akamaihd.net/economy/image/{$item['icon_url']}"
                    : null,
                'tradable'   => $item['tradable'] ?? 0,
                'marketable' => $item['marketable'] ?? 0,
                'type'       => $item['type'] ?? null,
                'exterior'   => $item['market_hash_name'] ?? null,
            ];
        })->filter(function ($item) use ($query) {
            return
                str_contains(strtolower($item['name']), $query) ||
                ($item['type'] && str_contains(strtolower($item['type']), $query)) ||
                ($item['exterior'] && str_contains(strtolower($item['exterior']), $query)) ||
                ($query === "tradeable" && $item['tradable']) ||
                ($query === "marketable" && $item['marketable']) ||
                ($query === "no trade" && !$item['tradable']) ||
                ($query === "no market" && !$item['marketable']);
        })->values();

        return response()->json([
            'total'        => $items->count(),
            'items'        => $items,
            'from_cache'   => true,
        ]);
    }

    public function searchInventory(Request $request, $steamid)
    {
        $appid = 730;
        $contextid = 2;
        $lang = 'spanish';
        $query = strtolower($request->query('q', ''));
        $allDescriptions = $this->fetchAllInventoryAndCache($steamid, $appid, $contextid, $lang);

        $items = collect($allDescriptions)->map(function ($item) use ($appid) {
            $marketName = $item['market_hash_name'] ?? $item['name'];
            return [
                'name'       => $item['name'] ?? 'Sin nombre',
                'marketName' => $marketName,
                'image'      => isset($item['icon_url'])
                    ? "https://steamcommunity-a.akamaihd.net/economy/image/{$item['icon_url']}"
                    : null,
                'tradable'   => $item['tradable'] ?? 0,
                'marketable' => $item['marketable'] ?? 0,
                'type'       => $item['type'] ?? null,
                'exterior'   => $item['market_hash_name'] ?? null,
            ];
        })->filter(function ($item) use ($query) {
            return
                str_contains(strtolower($item['name']), $query) ||
                ($item['type'] && str_contains(strtolower($item['type']), $query)) ||
                ($item['exterior'] && str_contains(strtolower($item['exterior']), $query)) ||
                ($query === "tradeable" && $item['tradable']) ||
                ($query === "marketable" && $item['marketable']) ||
                ($query === "no trade" && !$item['tradable']) ||
                ($query === "no market" && !$item['marketable']);
        })->values();

        return response()->json([
            'total'        => $items->count(),
            'items'        => $items,
            'from_cache'   => false,
        ]);
    }
}
