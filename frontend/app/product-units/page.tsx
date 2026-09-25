"use client";

import { FormEvent, useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { createProductUnit, getCustomers, getProductUnits, getProducts, getToken, type Customer, type Product, type ProductUnit } from "../lib/api";

export default function ProductUnitsPage() {
    const router = useRouter();
    const [units, setUnits] = useState<ProductUnit[]>([]);
    const [products, setProducts] = useState<Product[]>([]);
    const [customers, setCustomers] = useState<Customer[]>([]);
    const [isFormOpen, setIsFormOpen] = useState(false);
    const [isLoading, setIsLoading] = useState(true);
    const [isSaving, setIsSaving] = useState(false);
    const [error, setError] = useState("");
    const [formError, setFormError] = useState("");

    useEffect(() => {
        if (!getToken()) {
            router.replace("/login");
            return;
        }
        Promise.all([getProductUnits(), getProducts(), getCustomers()])
            .then(([loadedUnits, loadedProducts, loadedCustomers]) => {
                setUnits(loadedUnits);
                setProducts(loadedProducts);
                setCustomers(loadedCustomers);
            })
            .catch((requestError: unknown) => setError(requestError instanceof Error ? requestError.message : "Data unit produk tidak dapat dimuat."))
            .finally(() => setIsLoading(false));
    }, [router]);

    async function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setIsSaving(true);
        setFormError("");
        const formData = new FormData(event.currentTarget);
        try {
            await createProductUnit({
                product_id: Number(formData.get("product_id")),
                customer_id: Number(formData.get("customer_id")),
                serial_number: String(formData.get("serial_number")),
                purchase_date: String(formData.get("purchase_date") || ""),
            });
            setUnits(await getProductUnits());
            setIsFormOpen(false);
            event.currentTarget.reset();
        } catch (requestError) {
            setFormError(requestError instanceof Error ? requestError.message : "Unit produk tidak dapat disimpan.");
        } finally {
            setIsSaving(false);
        }
    }

    return (
        <main className="claims-page">
            <header className="page-header">
                <Link className="back-link" href="/">← Dashboard</Link>
                <div className="page-header-content"><div><p className="dashboard-kicker">MASTER DATA</p><h1>Unit produk</h1><p>Daftarkan serial number dan hubungkan dengan produk serta pelanggan.</p></div><button className="primary-action" type="button" onClick={() => { setFormError(""); setIsFormOpen(true); }}>+ Tambah unit</button></div>
            </header>
            <section className="claims-page-content">
                {error && <p className="form-error" role="alert">{error}</p>}
                <section className="claims-list-panel"><div className="claims-list-toolbar"><div><h2>Semua unit produk</h2><span>{units.length} data ditemukan</span></div></div><div className="claims-table-wrap">
                    {isLoading ? <div className="empty-state"><strong>Memuat unit produk...</strong></div> : units.length === 0 ? <div className="empty-state"><strong>Belum ada unit produk</strong><span>Tambahkan unit produk untuk mulai membuat garansi.</span></div> : <table className="claims-table claims-page-table"><thead><tr><th>Serial number</th><th>Produk</th><th>Pelanggan</th></tr></thead><tbody>{units.map((unit) => <tr key={unit.id}><td><strong>{unit.serial_number}</strong></td><td>{unit.product?.name || "-"}</td><td>{unit.customer?.name || "-"}</td></tr>)}</tbody></table>}
                </div></section>
            </section>
            {isFormOpen && <div className="modal-backdrop" role="presentation" onClick={() => setIsFormOpen(false)}><section className="claim-modal" role="dialog" aria-modal="true" aria-labelledby="unit-modal-title" onClick={(event) => event.stopPropagation()}><button className="modal-close" type="button" onClick={() => setIsFormOpen(false)} aria-label="Tutup">×</button><p className="dashboard-kicker">UNIT BARU</p><h2 id="unit-modal-title">Tambah unit produk</h2>{formError && <p className="form-error" role="alert">{formError}</p>}<form onSubmit={handleSubmit}><label>Produk<select name="product_id" required defaultValue=""><option value="" disabled>Pilih produk</option>{products.map((product) => <option key={product.id} value={product.id}>{product.name}</option>)}</select></label><label>Pelanggan<select name="customer_id" required defaultValue=""><option value="" disabled>Pilih pelanggan</option>{customers.map((customer) => <option key={customer.id} value={customer.id}>{customer.name}</option>)}</select></label><label>Nomor serial<input name="serial_number" required placeholder="Contoh: GT-AX91-001" /></label><label>Tanggal pembelian<input name="purchase_date" type="date" /></label><div className="modal-actions"><button className="secondary-action" type="button" onClick={() => setIsFormOpen(false)}>Batal</button><button className="primary-action" type="submit" disabled={isSaving}>{isSaving ? "Menyimpan..." : "Simpan unit"}</button></div></form></section></div>}
        </main>
    );
}