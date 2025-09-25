import { useEffect, useState } from "react";
import { motion } from "framer-motion";

interface SteamItem {
  name: string;
  marketName: string;
  image: string | null;
  tradable: number;
  marketable: number;
  type: string | null;
  exterior: string | null;
}

interface PriceInfo {
  lowest_price?: string;
  median_price?: string;
  volume?: string;
  message?: string;
}

export default function Welcome() {
  const [items, setItems] = useState<SteamItem[]>([]);
  const [prices, setPrices] = useState<Record<string, PriceInfo>>({});
  const [loadingPrice, setLoadingPrice] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [moreItems, setMoreItems] = useState(false);
  const [lastAssetId, setLastAssetId] = useState<string | null>(null);
  const [search, setSearch] = useState("");
  const [searching, setSearching] = useState(false);

  const steamid = "76561198262015349";
  const perPage = 100;

  const fetchInventory = (startAssetId?: string) => {
    setLoading(true);
    let url = `/api/inventory/${steamid}?per_page=${perPage}`;
    if (startAssetId) {
      url += `&start_assetid=${startAssetId}`;
    }

    fetch(url)
      .then((res) => res.json())
      .then((data) => {
        setItems((prev) => [...prev, ...(data.items || [])]);
        setMoreItems(data.more_items || false);
        setLastAssetId(data.last_assetid || null);
      })
      .finally(() => setLoading(false));
  };

    const searchInventory = (query: string) => {
    setSearching(true);
    fetch(`/api/inventory-search-cache/${steamid}?q=${encodeURIComponent(query)}&per_page=${perPage}`)
        .then((res) => res.json())
        .then((data) => {
        setItems(data.items || []);
        setSearching(false);
        setLoading(true);
        fetch(`/api/inventory-search/${steamid}?q=${encodeURIComponent(query)}&per_page=${perPage}`)
            .then((res) => res.json())
            .then((data) => {
            setItems(data.items || []);
            })
            .finally(() => setLoading(false));
        });
    };

  const fetchPrice = (marketName: string) => {
    if (prices[marketName]) return;
    setLoadingPrice(marketName);

    fetch(`/api/item-price?market_name=${encodeURIComponent(marketName)}`)
      .then((res) => res.json())
      .then((data) => {
        setPrices((prev) => ({ ...prev, [marketName]: data }));
      })
      .finally(() => setLoadingPrice(null));
  };

  const exteriorMap: Record<string, string> = {
  "Factory New": "Recién Fabricado",
  "Minimal Wear": "Casi Nuevo",
  "Field-Tested": "Probado en Combate",
  "Well-Worn": "Bastante Desgastado",
  "Battle-Scarred": "Desgastado por la Batalla"
};

  useEffect(() => {
    fetchInventory();
  }, []);

useEffect(() => {
    if (search.trim() === "") {
        setItems([]);
      fetchInventory();
    }
  }, [search]);


  return (
    <div className="p-6 bg-gradient-to-b min-h-screen from-[#0b0f1a] via-[#13223a] to-[#1d3a52] w-full flex justify-center flex-col items-center">
      <p>{loading ? 'Buscando en Steam...' : searching ? 'Buscando en cache...' : ''}</p>
      <h1 className="text-2xl font-bold mb-4 text-white">Inventario de Steam</h1>

      <input
        type="text"
        placeholder="Buscar por nombre, tipo, estado..."
        value={search}
        onChange={e => setSearch(e.target.value)}
        onKeyDown={e => {
            if (e.key === 'Enter') {
                searchInventory(search);
            }
        }}
        className="mb-6 px-4 py-2 rounded-lg w-2/3 bg-[#13223a] text-white border-2 border-yellow-500 focus:outline-none"
      />

      <div className="grid grid-cols-2 md:grid-cols-4 gap-4 w-2/3">
        {items.map((item, idx) => {
          const price = prices[item.marketName];
          return (
            <motion.div
              key={idx}
              initial={{ y: -50, opacity: 0 }}
              animate={{ y: 0, opacity: 1 }} 
              transition={{ duration: 0.2, delay: idx * 0.05 }}
              className="p-[2px] rounded-lg bg-gradient-to-r from-yellow-400 via-yellow-500 to-yellow-600 hover:shadow-md  cursor-pointer"
              onClick={() => fetchPrice(item.marketName)}
            >
            <div className="rounded-lg bg-[#0b0f1a] p-3 h-full hover:bg-[#13223a] transition-all">
              {item.image && (
                <div className="p-[2px] pb-[2px] rounded-lg bg-gradient-to-r from-yellow-400 via-yellow-500 to-yellow-600">
                    <img
                        src={item.image}
                        alt={item.name}
                        className="w-full h-32 object-contain mb-0 rounded-lg bg-[#13223a]"
                    />
                </div>
              )}
              <p className="font-semibold text-white mt-3">{item.name}</p>
              {item.exterior && (
                <p className="text-sm text-gray-400">{exteriorMap[item.exterior] || item.exterior}</p>
              )}
              <p className="text-xs text-gray-200">{item.type}</p>

              <div className="mt-1 flex gap-2 text-xs">
                <span
                  className={`px-2 py-1 rounded ${
                    item.tradable
                      ? "bg-green-100 text-green-700"
                      : "bg-red-100 text-red-700"
                  }`}
                >
                  {item.tradable ? "Tradeable" : "No Trade"}
                </span>
                <span
                  className={`px-2 py-1 rounded ${
                    item.marketable
                      ? "bg-blue-100 text-blue-700"
                      : "bg-gray-100 text-gray-500"
                  }`}
                >
                  {item.marketable ? "Marketable" : "No Market"}
                </span>
              </div>

              {/* Precios solo si los pedí */}
              {loadingPrice === item.marketName && (
                <div role="status" className="w-full flex justify-center p-3 pb-1">
                    <svg aria-hidden="true" className="w-8 h-8 text-gray-200 animate-spin dark:text-gray-600 fill-yellow-400" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
                        <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
                    </svg>
                    <span className="sr-only">Loading...</span>
                </div>
              )}
              {price && (
                <div className="mt-2 text-sm text-white">
                  {price.lowest_price && (
                    <p>
                      <strong>Precio:</strong> {price.lowest_price}
                    </p>
                  )}
                </div>
              )}
              {price && (
                <p className="text-red-100">{price.message}</p>
              )}
            </div>
            </motion.div>
          );
        })}
      </div>

      {moreItems && !search && (
        <div className="flex justify-center mt-6">
          <button
            onClick={() => fetchInventory(lastAssetId || undefined)}
            disabled={loading}
            className="px-4 py-2 bg-gradient-to-r from-yellow-400 via-yellow-500 to-yellow-600 text-white rounded-lg shadow hover:shadow-md disabled:bg-gray-400 cursor-pointer"
          >
            {loading ? "Cargando..." : "Cargar más"}
          </button>
        </div>
      )}
    </div>
  );
}
