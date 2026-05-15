<x-layouts.admin :title="($themeAppearance['admin_texts']['edit_product'] ?? 'Edit product').' - '.$themeSettings->site_name">
    @php $admin = $themeAppearance['admin_texts']; @endphp
    <div class="mb-6">
        <h1 class="font-display text-3xl uppercase tracking-[.12em] text-[#dcdcdc]">{{ $admin['edit_product'] }}</h1>
        <p class="mt-2 text-[#7b7b7b]">{{ $admin['update_product_copy'] }}</p>
    </div>

    <form action="{{ route('admin.products.update', $product) }}" method="POST" enctype="multipart/form-data" class="border border-[#2b2b2b] bg-[rgba(16,16,18,.88)] p-8">
        @csrf
        @method('PUT')
        @include('admin.products._form', ['product' => $product])
    </form>
</x-layouts.admin>
