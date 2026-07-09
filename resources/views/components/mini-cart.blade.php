<div class="mini-cart p-4 bg-white shadow-lg border">
    <h3>Mini Cart</h3>
    <p>Items: {{ $itemCount ?? 0 }}</p>
    <p>Subtotal: {{ $subtotal ?? 0 }}</p>
    <a href="{{ route('marketplace.cart') }}" class="btn">Checkout</a>
</div>
