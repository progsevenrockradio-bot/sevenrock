<x-layouts.admin :title="$admin['products_heading'].' - '.$themeSettings->site_name">
    @php $admin = $themeAppearance['admin_texts']; @endphp
    @if (session('status'))
        <div class="mb-6 border border-[#1e4d2b] bg-[rgba(16,64,30,.2)] px-4 py-3 text-sm text-[#b8e6c3]">
            {{ session('status') }}
        </div>
    @endif

    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">{{ $admin['products_heading'] }}</h1>
            <p class="mt-2 text-[#7b7b7b]">{{ $admin['products_copy'] ?? 'Editable shop catalog used by the public store pages.' }}</p>
        </div>
        <a href="{{ route('admin.products.create') }}" class="lucille-button-solid">{{ $admin['new_product'] }}</a>
    </div>

    <div class="overflow-hidden border border-[#2b2b2b] bg-[rgba(16,16,18,.88)]">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-[#2b2b2b] text-[#dcdcdc]">
                <tr>
                    <th class="px-5 py-4">{{ $admin['table_title'] }}</th>
                    <th class="px-5 py-4">{{ $admin['table_category'] }}</th>
                    <th class="px-5 py-4">{{ $admin['table_price'] }}</th>
                    <th class="px-5 py-4">{{ $admin['table_image'] }}</th>
                    <th class="px-5 py-4">{{ $admin['table_actions'] }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#2b2b2b] text-[#7b7b7b]">
                @forelse ($products as $product)
                    <tr class="hover:bg-[rgba(255,255,255,.02)]">
                        <td class="px-5 py-4 font-display text-[15px] uppercase tracking-[.08em] text-[#dcdcdc]">{{ $product->title }}</td>
                        <td class="px-5 py-4">{{ $product->category }}</td>
                        <td class="px-5 py-4">
                            @if ($product->regular_price !== null)
                                <del class="mr-2 text-[#7b7b7b]">£{{ number_format((float) $product->regular_price, 2) }}</del>
                            @endif
                            £{{ number_format((float) $product->price, 2) }}
                        </td>
                        <td class="px-5 py-4">{{ $product->image }}</td>
                        <td class="px-5 py-4">
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('admin.products.edit', $product) }}" class="lucille-button">{{ $admin['edit'] }}</a>
                                <form action="{{ route('admin.products.destroy', $product) }}" method="POST" onsubmit="return confirm('{{ $admin['delete_confirm_product'] }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="lucille-button-solid">{{ $admin['delete'] }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-10 text-center text-[#7b7b7b]">{{ $admin['no_products'] }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.admin>
