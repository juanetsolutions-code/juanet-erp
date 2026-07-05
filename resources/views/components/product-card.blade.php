@props([
    'product' => null,
    'isAlpine' => false
])

@if($isAlpine)
<!-- Alpine.js Dynamic Card Template -->
<div class="bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-5 rounded-2xl shadow-sm hover:shadow-md hover:-translate-y-1 transition duration-300 text-left flex flex-col justify-between group h-[540px]"
     x-data="{ inWishlist: false }"
     id="product-card-alpine-{{ rand(1000, 9999) }}">
    <div class="space-y-4">
        <!-- Thumbnail Section -->
        <div class="h-44 rounded-xl bg-slate-100 dark:bg-slate-950 border border-slate-200/40 dark:border-slate-800/80 flex flex-col justify-between p-4 relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-indigo-500/5 to-transparent"></div>
            
            <!-- Badges top row -->
            <div class="flex justify-between items-start z-10 w-full">
                <!-- Category Badge -->
                <span class="text-[9px] font-mono font-bold text-indigo-600 bg-indigo-500/10 px-2 py-1 rounded uppercase tracking-wider" 
                      x-text="product.category_name || 'Asset'"></span>
                
                <!-- Wishlist button -->
                <button @click="inWishlist = !inWishlist; 
                                 $dispatch('trigger-toast', { 
                                     message: inWishlist ? '✓ Added to Wishlist!' : 'Removed from Wishlist.', 
                                     type: inWishlist ? 'success' : 'info' 
                                 });" 
                        class="p-2 rounded-xl bg-white/80 dark:bg-slate-900/80 backdrop-blur-sm border border-slate-200/40 dark:border-slate-800/80 shadow-sm text-slate-400 hover:text-rose-500 transition cursor-pointer">
                    <svg class="w-4 h-4" :class="{'fill-rose-500 text-rose-500': inWishlist, 'text-slate-400': !inWishlist}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                    </svg>
                </button>
            </div>

            <!-- Labels top left overlay -->
            <div class="flex flex-col gap-1 z-10 self-start mt-1">
                <template x-if="product.is_featured">
                    <span class="text-[9px] font-mono font-bold text-amber-600 bg-amber-500/15 px-2 py-0.5 rounded uppercase tracking-wider">Featured</span>
                </template>
                <template x-if="product.is_best_seller">
                    <span class="text-[9px] font-mono font-bold text-emerald-600 bg-emerald-500/15 px-2 py-0.5 rounded uppercase tracking-wider">Best Seller</span>
                </template>
                <template x-if="product.is_new">
                    <span class="text-[9px] font-mono font-bold text-sky-600 bg-sky-500/15 px-2 py-0.5 rounded uppercase tracking-wider">New</span>
                </template>
                <template x-if="product.previous_price && product.previous_price > product.price">
                    <span class="text-[9px] font-mono font-bold text-rose-600 bg-rose-500/15 px-2 py-0.5 rounded uppercase tracking-wider" 
                          x-text="Math.round(((product.previous_price - product.price) / product.previous_price) * 100) + '% OFF'"></span>
                </template>
            </div>

            <!-- Visual Icon/Cover representation -->
            <div class="w-full flex justify-center items-center mt-1 z-0 opacity-45 group-hover:scale-105 transition duration-500 select-none">
                <span class="text-5xl" x-text="product.category_slug === 'laravel' || product.framework === 'Laravel' ? '🔴' : (product.category_slug === 'react' || product.framework === 'React' ? '⚛️' : (product.category_slug === 'nextjs' || product.framework === 'Next.js' ? '🌐' : '💻'))"></span>
            </div>

            <div class="z-10 w-full mt-auto flex justify-between items-center">
                <span class="text-[10px] font-mono text-slate-400 block tracking-tight" x-text="product.author || 'JUANET Core'"></span>
                <span class="text-[10px] font-mono text-indigo-500 font-bold bg-indigo-500/5 px-1.5 py-0.5 rounded" x-text="product.version || 'v1.0.0'"></span>
            </div>
        </div>

        <!-- Product details -->
        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <!-- Tech tags (limit 3) -->
                <div class="flex gap-1 overflow-hidden max-w-[70%]">
                    <template x-for="tech in (product.technology || []).slice(0, 3)">
                        <span class="text-[9px] font-mono bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 px-1.5 py-0.5 rounded" x-text="tech"></span>
                    </template>
                </div>
                <!-- Rating -->
                <div class="flex items-center text-amber-400 text-[10px] font-mono">
                    <span>★</span>
                    <span class="text-slate-700 dark:text-slate-300 ml-1 font-bold" x-text="product.rating"></span>
                    <span class="text-slate-400 ml-0.5" x-text="'(' + product.review_count + ')'"></span>
                </div>
            </div>

            <h3 class="text-sm font-bold text-slate-900 dark:text-white line-clamp-1 group-hover:text-indigo-600 transition duration-200" x-text="product.title"></h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 line-clamp-2 leading-relaxed" x-text="product.short_description"></p>

            <!-- Metadata Row -->
            <div class="flex items-center justify-between pt-2 text-[10px] font-mono text-slate-400 border-t border-slate-50/50 dark:border-slate-800/20">
                <div class="flex items-center gap-1">
                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                    <span x-text="(product.downloads || '0') + ' dl'"></span>
                </div>
                <div class="flex items-center gap-1">
                    <span x-text="product.license || 'Regular License'" class="truncate max-w-[80px]"></span>
                </div>
                <div class="flex items-center gap-1">
                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /></svg>
                    <span x-text="product.views || '0'"></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Pricing and actions -->
    <div class="pt-4 border-t border-slate-100 dark:border-slate-800/80 mt-auto flex items-center justify-between">
        <div>
            <span class="text-[9px] text-slate-400 block uppercase font-mono tracking-wider" x-text="product.license || 'Instant Access'"></span>
            <div class="flex items-baseline gap-1.5">
                <span class="text-xs font-mono text-slate-400">KES</span>
                <span class="text-sm font-black text-slate-900 dark:text-white" x-text="Number(product.price).toLocaleString()"></span>
                <template x-if="product.previous_price">
                    <span class="text-[10px] line-through text-slate-400" x-text="'KES ' + Number(product.previous_price).toLocaleString()"></span>
                </template>
            </div>
        </div>
        <div class="flex gap-2">
            <button @click="openQuickPreview(product)" 
                    class="px-2.5 py-1.5 text-[10px] font-bold text-slate-700 bg-slate-50 border border-slate-200 hover:bg-slate-100 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300 rounded-lg transition cursor-pointer">
                Preview
            </button>
            <button @click="openDetails(product)" 
                    class="px-3 py-1.5 text-[10px] font-bold text-white bg-indigo-600 hover:bg-indigo-500 rounded-lg transition cursor-pointer">
                Details
            </button>
        </div>
    </div>
</div>
@else
<!-- Server-Side Fallback Blade Card -->
<div class="bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-5 rounded-2xl shadow-sm hover:shadow-md hover:-translate-y-1 transition duration-300 text-left flex flex-col justify-between group h-[540px]"
     x-data="{ inWishlist: false }"
     id="product-card-server-{{ $product->id ?? rand(1000, 9999) }}">
    <div class="space-y-4">
        <!-- Thumbnail Section -->
        <div class="h-44 rounded-xl bg-slate-100 dark:bg-slate-950 border border-slate-200/40 dark:border-slate-800/80 flex flex-col justify-between p-4 relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-indigo-500/5 to-transparent"></div>
            
            <div class="flex justify-between items-start z-10 w-full">
                <span class="text-[9px] font-mono font-bold text-indigo-600 bg-indigo-500/10 px-2 py-1 rounded uppercase tracking-wider">
                    {{ $product->category_name ?? 'Asset' }}
                </span>
                
                <button @click="inWishlist = !inWishlist; 
                                 $dispatch('trigger-toast', { 
                                     message: inWishlist ? '✓ Added to Wishlist!' : 'Removed from Wishlist.', 
                                     type: inWishlist ? 'success' : 'info' 
                                 });" 
                        class="p-2 rounded-xl bg-white/80 dark:bg-slate-900/80 backdrop-blur-sm border border-slate-200/40 dark:border-slate-800/80 shadow-sm text-slate-400 hover:text-rose-500 transition cursor-pointer">
                    <svg class="w-4 h-4" :class="{'fill-rose-500 text-rose-500': inWishlist, 'text-slate-400': !inWishlist}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                    </svg>
                </button>
            </div>

            <div class="flex flex-col gap-1 z-10 self-start mt-1">
                @if($product->is_featured ?? false)
                    <span class="text-[9px] font-mono font-bold text-amber-600 bg-amber-500/15 px-2 py-0.5 rounded uppercase tracking-wider">Featured</span>
                @endif
                @if($product->is_best_seller ?? false)
                    <span class="text-[9px] font-mono font-bold text-emerald-600 bg-emerald-500/15 px-2 py-0.5 rounded uppercase tracking-wider">Best Seller</span>
                @endif
                @if($product->is_new ?? false)
                    <span class="text-[9px] font-mono font-bold text-sky-600 bg-sky-500/15 px-2 py-0.5 rounded uppercase tracking-wider">New</span>
                @endif
                @if(($product->previous_price ?? 0) > ($product->price ?? 0))
                    <span class="text-[9px] font-mono font-bold text-rose-600 bg-rose-500/15 px-2 py-0.5 rounded uppercase tracking-wider">
                        {{ round((($product->previous_price - $product->price) / $product->previous_price) * 100) }}% OFF
                    </span>
                @endif
            </div>

            <div class="w-full flex justify-center items-center mt-2 z-0 opacity-40 group-hover:scale-105 transition duration-500 select-none">
                <span class="text-5xl">
                    @if(($product->category_slug ?? '') === 'laravel' || ($product->framework ?? '') === 'Laravel') 🔴 
                    @elseif(($product->category_slug ?? '') === 'react' || ($product->framework ?? '') === 'React') ⚛️ 
                    @elseif(($product->category_slug ?? '') === 'nextjs' || ($product->framework ?? '') === 'Next.js') 🌐 
                    @else 💻 @endif
                </span>
            </div>

            <div class="z-10 w-full mt-auto flex justify-between items-center">
                <span class="text-[10px] font-mono text-slate-400 block tracking-tight">{{ $product->author ?? 'JUANET Core' }}</span>
                <span class="text-[10px] font-mono text-indigo-500 font-bold bg-indigo-500/5 px-1.5 py-0.5 rounded">{{ $product->version ?? 'v1.0.0' }}</span>
            </div>
        </div>

        <!-- Details -->
        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <div class="flex gap-1 overflow-hidden max-w-[70%]">
                    @if(isset($product->technology) && is_array($product->technology))
                        @foreach(array_slice($product->technology, 0, 3) as $tech)
                            <span class="text-[9px] font-mono bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 px-1.5 py-0.5 rounded">{{ $tech }}</span>
                        @endforeach
                    @endif
                </div>
                <div class="flex items-center text-amber-400 text-[10px] font-mono">
                    <span>★</span>
                    <span class="text-slate-700 dark:text-slate-300 ml-1 font-bold">{{ $product->rating ?? '5.0' }}</span>
                    <span class="text-slate-400 ml-0.5">({{ $product->review_count ?? '0' }})</span>
                </div>
            </div>

            <h3 class="text-sm font-bold text-slate-900 dark:text-white line-clamp-1 group-hover:text-indigo-600 transition duration-200">{{ $product->title }}</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 line-clamp-2 leading-relaxed">{{ $product->short_description }}</p>

            <!-- Metadata Row -->
            <div class="flex items-center justify-between pt-2 text-[10px] font-mono text-slate-400 border-t border-slate-50/50 dark:border-slate-800/20">
                <div class="flex items-center gap-1">
                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                    <span>{{ $product->downloads ?? '120' }} dl</span>
                </div>
                <div class="flex items-center gap-1">
                    <span class="truncate max-w-[80px]">{{ $product->license ?? 'Regular License' }}</span>
                </div>
                <div class="flex items-center gap-1">
                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /></svg>
                    <span>{{ $product->views ?? '924' }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Pricing / Actions -->
    <div class="pt-4 border-t border-slate-100 dark:border-slate-800/80 mt-auto flex items-center justify-between">
        <div>
            <span class="text-[9px] text-slate-400 block uppercase font-mono tracking-wider">{{ $product->license ?? 'Instant Access' }}</span>
            <div class="flex items-baseline gap-1.5">
                <span class="text-xs font-mono text-slate-400">KES</span>
                <span class="text-sm font-black text-slate-900 dark:text-white">{{ number_format($product->price ?? 0) }}</span>
                @if($product->previous_price ?? false)
                    <span class="text-[10px] line-through text-slate-400">KES {{ number_format($product->previous_price) }}</span>
                @endif
            </div>
        </div>
        <div class="flex gap-2">
            <button @click="openQuickPreview({{ json_encode($product) }})" 
                    class="px-2.5 py-1.5 text-[10px] font-bold text-slate-700 bg-slate-50 border border-slate-200 hover:bg-slate-100 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300 rounded-lg transition cursor-pointer">
                Preview
            </button>
            <button @click="openDetails({{ json_encode($product) }})" 
                    class="px-3 py-1.5 text-[10px] font-bold text-white bg-indigo-600 hover:bg-indigo-500 rounded-lg transition cursor-pointer">
                Details
            </button>
        </div>
    </div>
</div>
@endif
