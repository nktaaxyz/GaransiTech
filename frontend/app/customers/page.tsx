"use client";

import { FormEvent, useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { createCustomer, getCustomers, getToken, type Customer } from "../lib/api";

export default function CustomersPage() {
    const router = useRouter();
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
        getCustomers()
            .then(setCustomers)
            .catch((requestError: unknown) => setError(requestError instanceof Error ? requestError.message : "Data pelanggan tidak dapat dimuat."))
            .finally(() => setIsLoading(false));
    }, [router]);

    async function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setIsSaving(true);
        setFormError("");
        const formData = new FormData(event.currentTarget);
        try {
            await createCustomer({
                name: String(formData.get("name")),
                phone: String(formData.get("phone")),
                address: String(formData.get("address") || ""),
            });
            setCustomers(await getCustomers());
            setIsFormOpen(false);
            event.currentTarget.reset();
        } catch (requestError) {
            setFormError(requestError instanceof Error ? requestError.message : "Pelanggan tidak dapat disimpan.");
        } finally {
            setIsSaving(false);
        }
    }

    return (
        <main className="claims-page">
            <header className="page-header">
                <Link className="back-link" href="/">← Dashboard</Link>
                <div className="page-header-content"><div><p className="dashboard-kicker">MASTER DATA</p><h1>Pelanggan</h1><p>Kelola data pelanggan sebelum mendaftarkan unit produk.</p></div><button className="primary-action" type="button" onClick={() => { setFormError(""); setIsFormOpen(true); }}>+ Tambah pelanggan</button></div>
            </header>
            <section className="claims-page-content">
                {error && <p className="form-error" role="alert">{error}</p>}
                <section className="claims-list-panel"><div className="claims-list-toolbar"><div><h2>Semua pelanggan</h2><span>{customers.length} data ditemukan</span></div></div><div className="claims-table-wrap">
                    {isLoading ? <div className="empty-state"><strong>Memuat pelanggan...</strong></div> : customers.length === 0 ? <div className="empty-state"><strong>Belum ada pelanggan</strong><span>Tambahkan pelanggan untuk mulai membuat unit produk.</span></div> : <table className="claims-table claims-page-table"><thead><tr><th>Nama</th><th>Telepon</th><th>Alamat</th><th>Jumlah unit</th></tr></thead><tbody>{customers.map((customer) => <tr key={customer.id}><td><strong>{customer.name}</strong></td><td>{customer.phone}</td><td>{customer.address || "-"}</td><td>{customer.product_units_count ?? 0}</td></tr>)}</tbody></table>}
                </div></section>
            </section>
            {isFormOpen && <div className="modal-backdrop" role="presentation" onClick={() => setIsFormOpen(false)}><section className="claim-modal" role="dialog" aria-modal="true" aria-labelledby="customer-modal-title" onClick={(event) => event.stopPropagation()}><button className="modal-close" type="button" onClick={() => setIsFormOpen(false)} aria-label="Tutup">×</button><p className="dashboard-kicker">PELANGGAN BARU</p><h2 id="customer-modal-title">Tambah pelanggan</h2>{formError && <p className="form-error" role="alert">{formError}</p>}<form onSubmit={handleSubmit}><label>Nama pelanggan<input name="name" required /></label><label>Nomor telepon<input name="phone" required /></label><label>Alamat<textarea name="address" rows={3} /></label><div className="modal-actions"><button className="secondary-action" type="button" onClick={() => setIsFormOpen(false)}>Batal</button><button className="primary-action" type="submit" disabled={isSaving}>{isSaving ? "Menyimpan..." : "Simpan pelanggan"}</button></div></form></section></div>}
        </main>
    );
}