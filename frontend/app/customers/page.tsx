"use client";

import { FormEvent, useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { createCustomer, deleteCustomer, getCustomers, getToken, updateCustomer, type Customer } from "../lib/api";

export default function CustomersPage() {
    const router = useRouter();
    const [customers, setCustomers] = useState<Customer[]>([]);
    const [isFormOpen, setIsFormOpen] = useState(false);
    const [editingCustomer, setEditingCustomer] = useState<Customer | null>(null);
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
            const input = {
                name: String(formData.get("name")),
                phone: String(formData.get("phone")),
                address: String(formData.get("address") || ""),
            };
            if (editingCustomer) await updateCustomer(editingCustomer.id, input);
            else await createCustomer(input);
            setCustomers(await getCustomers());
            setIsFormOpen(false);
            setEditingCustomer(null);
            event.currentTarget.reset();
        } catch (requestError) {
            setFormError(requestError instanceof Error ? requestError.message : "Pelanggan tidak dapat disimpan.");
        } finally {
            setIsSaving(false);
        }
    }

    async function handleDelete(customer: Customer) {
        if (!window.confirm(`Hapus pelanggan ${customer.name}?`)) return;
        try {
            setError("");
            await deleteCustomer(customer.id);
            setCustomers(await getCustomers());
        } catch (requestError) {
            setError(requestError instanceof Error ? requestError.message : "Pelanggan tidak dapat dihapus.");
        }
    }

    return (
        <main className="claims-page">
            <header className="page-header">
                <Link className="back-link" href="/">← Dashboard</Link>
                <div className="page-header-content"><div><p className="dashboard-kicker">MASTER DATA</p><h1>Pelanggan</h1><p>Kelola data pelanggan sebelum mendaftarkan unit produk.</p></div><button className="primary-action" type="button" onClick={() => { setEditingCustomer(null); setFormError(""); setIsFormOpen(true); }}>+ Tambah pelanggan</button></div>
            </header>
            <section className="claims-page-content">
                {error && <p className="form-error" role="alert">{error}</p>}
                <section className="claims-list-panel"><div className="claims-list-toolbar"><div><h2>Semua pelanggan</h2><span>{customers.length} data ditemukan</span></div></div><div className="claims-table-wrap">
                    {isLoading ? <div className="empty-state"><strong>Memuat pelanggan...</strong></div> : customers.length === 0 ? <div className="empty-state"><strong>Belum ada pelanggan</strong><span>Tambahkan pelanggan untuk mulai membuat unit produk.</span></div> : <table className="claims-table claims-page-table"><thead><tr><th>Nama</th><th>Telepon</th><th>Alamat</th><th>Jumlah unit</th><th /></tr></thead><tbody>{customers.map((customer) => <tr key={customer.id}><td><strong>{customer.name}</strong></td><td>{customer.phone}</td><td>{customer.address || "-"}</td><td>{customer.product_units_count ?? 0}</td><td><button className="table-action" type="button" onClick={() => { setEditingCustomer(customer); setFormError(""); setIsFormOpen(true); }}>Edit</button><button className="table-action table-action-danger" type="button" onClick={() => handleDelete(customer)}>Hapus</button></td></tr>)}</tbody></table>}
                </div></section>
            </section>
            {isFormOpen && <div className="modal-backdrop" role="presentation" onClick={() => { setIsFormOpen(false); setEditingCustomer(null); }}><section className="claim-modal" role="dialog" aria-modal="true" aria-labelledby="customer-modal-title" onClick={(event) => event.stopPropagation()}><button className="modal-close" type="button" onClick={() => { setIsFormOpen(false); setEditingCustomer(null); }} aria-label="Tutup">×</button><p className="dashboard-kicker">{editingCustomer ? "EDIT PELANGGAN" : "PELANGGAN BARU"}</p><h2 id="customer-modal-title">{editingCustomer ? "Edit pelanggan" : "Tambah pelanggan"}</h2>{formError && <p className="form-error" role="alert">{formError}</p>}<form onSubmit={handleSubmit}><label>Nama pelanggan<input name="name" required defaultValue={editingCustomer?.name || ""} /></label><label>Nomor telepon<input name="phone" required defaultValue={editingCustomer?.phone || ""} /></label><label>Alamat<textarea name="address" rows={3} defaultValue={editingCustomer?.address || ""} /></label><div className="modal-actions"><button className="secondary-action" type="button" onClick={() => { setIsFormOpen(false); setEditingCustomer(null); }}>Batal</button><button className="primary-action" type="submit" disabled={isSaving}>{isSaving ? "Menyimpan..." : editingCustomer ? "Simpan perubahan" : "Simpan pelanggan"}</button></div></form></section></div>}
        </main>
    );
}