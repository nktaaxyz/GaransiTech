"use client";

import { FormEvent, useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { createProduct, deleteProduct, getProducts, getToken, getVendors, updateProduct, type Product, type Vendor } from "../lib/api";

export default function ProductsPage() {
    const router = useRouter();
    const [products, setProducts] = useState<Product[]>([]);
    const [vendors, setVendors] = useState<Vendor[]>([]);
    const [editingProduct, setEditingProduct] = useState<Product | null>(null);
    const [isFormOpen, setIsFormOpen] = useState(false);
    const [isLoading, setIsLoading] = useState(true);
    const [isSaving, setIsSaving] = useState(false);
    const [error, setError] = useState("");
    const [formError, setFormError] = useState("");

    async function refresh() { setProducts(await getProducts()); }

    useEffect(() => {
        if (!getToken()) { router.replace("/login"); return; }
        Promise.all([getProducts(), getVendors()]).then(([loadedProducts, loadedVendors]) => { setProducts(loadedProducts); setVendors(loadedVendors); }).catch((requestError: unknown) => setError(requestError instanceof Error ? requestError.message : "Data produk tidak dapat dimuat.")).finally(() => setIsLoading(false));
    }, [router]);

    async function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault(); setIsSaving(true); setFormError("");
        const data = new FormData(event.currentTarget);
        const input = { name: String(data.get("name")), category: String(data.get("category") || ""), vendor_id: Number(data.get("vendor_id")) };
        try { if (editingProduct) await updateProduct(editingProduct.id, input); else await createProduct(input); await refresh(); closeForm(); } catch (requestError) { setFormError(requestError instanceof Error ? requestError.message : "Produk tidak dapat disimpan."); } finally { setIsSaving(false); }
    }

    async function handleDelete(product: Product) {
        if (!window.confirm(`Hapus produk ${product.name}?`)) return;
        try { setError(""); await deleteProduct(product.id); await refresh(); } catch (requestError) { setError(requestError instanceof Error ? requestError.message : "Produk tidak dapat dihapus."); }
    }

    function closeForm() { setIsFormOpen(false); setEditingProduct(null); setFormError(""); }

    return (
        <main className="claims-page">
            <header className="page-header"><Link className="back-link" href="/">← Dashboard</Link><div className="page-header-content"><div><p className="dashboard-kicker">MASTER DATA</p><h1>Produk</h1><p>Daftarkan produk sebelum membuat unit produk.</p></div><button className="primary-action" type="button" onClick={() => { setEditingProduct(null); setIsFormOpen(true); }}>+ Tambah produk</button></div></header>
            <section className="claims-page-content">{error && <p className="form-error" role="alert">{error}</p>}<section className="claims-list-panel"><div className="claims-list-toolbar"><div><h2>Semua produk</h2><span>{products.length} data ditemukan</span></div></div><div className="claims-table-wrap">{isLoading ? <div className="empty-state"><strong>Memuat produk...</strong></div> : products.length === 0 ? <div className="empty-state"><strong>Belum ada produk</strong><span>Tambahkan produk untuk membuat unit produk.</span></div> : <table className="claims-table claims-page-table"><thead><tr><th>Nama produk</th><th>Kategori</th><th>Vendor</th><th>Jumlah unit</th><th /></tr></thead><tbody>{products.map((product) => <tr key={product.id}><td><strong>{product.name}</strong></td><td>{product.category || "-"}</td><td>{product.vendor?.name || "-"}</td><td>{product.units_count ?? 0}</td><td><button className="table-action" type="button" onClick={() => { setEditingProduct(product); setIsFormOpen(true); }}>Edit</button><button className="table-action table-action-danger" type="button" onClick={() => handleDelete(product)}>Hapus</button></td></tr>)}</tbody></table>}</div></section></section>
            {isFormOpen && <div className="modal-backdrop" role="presentation" onClick={closeForm}><section className="claim-modal" role="dialog" aria-modal="true" aria-labelledby="product-modal-title" onClick={(event) => event.stopPropagation()}><button className="modal-close" type="button" onClick={closeForm} aria-label="Tutup">×</button><p className="dashboard-kicker">{editingProduct ? "EDIT PRODUK" : "PRODUK BARU"}</p><h2 id="product-modal-title">{editingProduct ? "Edit produk" : "Tambah produk"}</h2>{formError && <p className="form-error" role="alert">{formError}</p>}<form onSubmit={handleSubmit}><label>Nama produk<input name="name" required defaultValue={editingProduct?.name || ""} /></label><label>Kategori<input name="category" defaultValue={editingProduct?.category || ""} /></label><label>Vendor<select name="vendor_id" required defaultValue={editingProduct?.vendor?.id?.toString() || ""}><option value="" disabled>Pilih vendor</option>{vendors.map((vendor) => <option key={vendor.id} value={vendor.id}>{vendor.name}</option>)}</select></label><div className="modal-actions"><button className="secondary-action" type="button" onClick={closeForm}>Batal</button><button className="primary-action" type="submit" disabled={isSaving}>{isSaving ? "Menyimpan..." : editingProduct ? "Simpan perubahan" : "Simpan produk"}</button></div></form></section></div>}
        </main>
    );
}
