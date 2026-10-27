"use client";

import { FormEvent, useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import {
    createPaidServiceRequest,
    getExpiredWarrantyUnits,
    getPaidServiceRequests,
    getToken,
    getVendors,
    updatePaidServiceRequest,
    type PaidServiceRequest,
    type ProductUnit,
    type Vendor,
} from "../lib/api";
import { TableSkeleton } from "../components/LoadingState";

const statusLabels: Record<PaidServiceRequest["status"], string> = {
    requested: "Permintaan baru",
    quoted: "Menunggu persetujuan biaya",
    approved: "Biaya disetujui",
    in_progress: "Sedang diservis",
    completed: "Selesai",
    cancelled: "Dibatalkan",
};

const serviceStatuses = Object.entries(statusLabels) as [PaidServiceRequest["status"], string][];

function formatAmount(amount: string | null): string {
    if (amount === null) return "Belum ada";
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(Number(amount));
}

export default function PaidServicesPage() {
    const router = useRouter();
    const [requests, setRequests] = useState<PaidServiceRequest[]>([]);
    const [eligibleUnits, setEligibleUnits] = useState<ProductUnit[]>([]);
    const [vendors, setVendors] = useState<Vendor[]>([]);
    const [page, setPage] = useState(1);
    const [lastPage, setLastPage] = useState(1);
    const [total, setTotal] = useState(0);
    const [editingRequest, setEditingRequest] = useState<PaidServiceRequest | null>(null);
    const [isFormOpen, setIsFormOpen] = useState(false);
    const [isLoading, setIsLoading] = useState(true);
    const [isSaving, setIsSaving] = useState(false);
    const [error, setError] = useState("");
    const [formError, setFormError] = useState("");

    async function refreshRequests(nextPage = page) {
        const result = await getPaidServiceRequests(nextPage);
        setRequests(result.data);
        setPage(result.current_page);
        setLastPage(result.last_page);
        setTotal(result.total);
    }

    useEffect(() => {
        if (!getToken()) {
            router.replace("/login");
            return;
        }
        Promise.all([getPaidServiceRequests(), getExpiredWarrantyUnits(), getVendors()])
            .then(([serviceRequests, units, loadedVendors]) => {
                setRequests(serviceRequests.data);
                setPage(serviceRequests.current_page);
                setLastPage(serviceRequests.last_page);
                setTotal(serviceRequests.total);
                setEligibleUnits(units);
                setVendors(loadedVendors);
            })
            .catch((requestError: unknown) => setError(requestError instanceof Error ? requestError.message : "Data servis berbayar tidak dapat dimuat."))
            .finally(() => setIsLoading(false));
    }, [router]);

    async function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setIsSaving(true);
        setFormError("");
        const data = new FormData(event.currentTarget);
        const vendorId = String(data.get("vendor_id") || "");
        const amount = String(data.get("quoted_amount") || "");
        const input = {
            vendor_id: vendorId ? Number(vendorId) : null,
            issue_description: String(data.get("issue_description")),
            quoted_amount: amount ? Number(amount) : null,
            notes: String(data.get("notes") || ""),
        };
        try {
            if (editingRequest) {
                await updatePaidServiceRequest(editingRequest.id, {
                    ...input,
                    status: String(data.get("status")) as PaidServiceRequest["status"],
                });
            } else {
                await createPaidServiceRequest({
                    ...input,
                    product_unit_id: Number(data.get("product_unit_id")),
                });
            }
            await refreshRequests();
            closeForm();
        } catch (requestError) {
            setFormError(requestError instanceof Error ? requestError.message : "Permintaan servis tidak dapat disimpan.");
        } finally {
            setIsSaving(false);
        }
    }

    function closeForm() {
        setIsFormOpen(false);
        setEditingRequest(null);
        setFormError("");
    }

    return (
        <main className="claims-page">
            <header className="page-header">
                <Link className="back-link" href="/">← Dashboard</Link>
                <div className="page-header-content">
                    <div><p className="dashboard-kicker">LAYANAN PELANGGAN</p><h1>Servis berbayar</h1><p>Catat dan pantau servis untuk unit yang masa garansinya sudah berakhir.</p></div>
                    <button className="primary-action" type="button" onClick={() => { setEditingRequest(null); setFormError(""); setIsFormOpen(true); }}>+ Buat permintaan servis</button>
                </div>
            </header>
            <section className="claims-page-content">
                {error && <p className="form-error" role="alert">{error}</p>}
                <section className="claims-list-panel">
                    <div className="claims-list-toolbar"><div><h2>Permintaan servis</h2><span>{total} data ditemukan</span></div></div>
                    <div className="claims-table-wrap">
                        {isLoading ? <TableSkeleton rows={5} columns={6} /> : requests.length === 0 ? (
                            <div className="empty-state"><strong>Belum ada permintaan servis</strong><span>Buat permintaan untuk unit yang garansinya sudah berakhir.</span></div>
                        ) : (
                            <table className="claims-table claims-page-table">
                                <thead><tr><th>Unit / pelanggan</th><th>Produk</th><th>Mitra servis</th><th>Estimasi biaya</th><th>Status</th><th /></tr></thead>
                                <tbody>{requests.map((request) => (
                                    <tr key={request.id}>
                                        <td><strong>{request.product_unit.serial_number}</strong><span className="table-secondary">{request.product_unit.customer?.name || "Pelanggan tidak tersedia"}</span></td>
                                        <td>{request.product_unit.product?.name || "-"}</td>
                                        <td>{request.vendor?.name || "Belum ditentukan"}</td>
                                        <td>{formatAmount(request.quoted_amount)}</td>
                                        <td>{statusLabels[request.status]}</td>
                                        <td><button className="table-action" type="button" onClick={() => { setEditingRequest(request); setFormError(""); setIsFormOpen(true); }}>Kelola</button></td>
                                    </tr>
                                ))}</tbody>
                            </table>
                        )}
                    </div>
                    {!isLoading && lastPage > 1 && <nav className="pagination-controls" aria-label="Halaman permintaan servis"><button className="secondary-action" type="button" disabled={page <= 1} onClick={() => refreshRequests(page - 1)}>Sebelumnya</button><span>Halaman {page} dari {lastPage}</span><button className="secondary-action" type="button" disabled={page >= lastPage} onClick={() => refreshRequests(page + 1)}>Berikutnya</button></nav>}
                </section>
            </section>
            {isFormOpen && <div className="modal-backdrop" role="presentation" onClick={closeForm}>
                <section className="claim-modal" role="dialog" aria-modal="true" aria-labelledby="service-modal-title" onClick={(event) => event.stopPropagation()}>
                    <button className="modal-close" type="button" onClick={closeForm} aria-label="Tutup">×</button>
                    <p className="dashboard-kicker">{editingRequest ? "KELOLA SERVIS" : "SERVIS BARU"}</p>
                    <h2 id="service-modal-title">{editingRequest ? "Kelola permintaan servis" : "Buat permintaan servis"}</h2>
                    <p>Servis berbayar hanya tersedia untuk unit yang garansinya sudah berakhir.</p>
                    {formError && <p className="form-error" role="alert">{formError}</p>}
                    <form onSubmit={handleSubmit}>
                        {editingRequest ? (
                            <label>Unit produk<input value={`${editingRequest.product_unit.serial_number} — ${editingRequest.product_unit.customer?.name || "Pelanggan"}`} disabled /></label>
                        ) : (
                            <label>Unit produk<select name="product_unit_id" required defaultValue=""><option value="" disabled>Pilih unit bergaransi habis</option>{eligibleUnits.map((unit) => <option key={unit.id} value={unit.id}>{unit.serial_number} — {unit.product?.name || "Produk"} ({unit.customer?.name || "Pelanggan"})</option>)}</select></label>
                        )}
                        {!editingRequest && eligibleUnits.length === 0 && <p role="status">Belum ada unit dengan garansi berakhir yang dapat dipilih.</p>}
                        <label>Keluhan / kebutuhan servis<textarea name="issue_description" rows={3} required defaultValue={editingRequest?.issue_description || ""} /></label>
                        <label>Mitra servis<select name="vendor_id" defaultValue={editingRequest?.vendor?.id?.toString() || ""}><option value="">Belum ditentukan</option>{vendors.map((vendor) => <option key={vendor.id} value={vendor.id}>{vendor.name}</option>)}</select></label>
                        <label>Estimasi biaya (rupiah)<input name="quoted_amount" type="number" min="0" step="1" defaultValue={editingRequest?.quoted_amount || ""} /></label>
                        {editingRequest && <label>Status<select name="status" defaultValue={editingRequest.status}>{serviceStatuses.map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></label>}
                        <label>Catatan<textarea name="notes" rows={2} defaultValue={editingRequest?.notes || ""} /></label>
                        <div className="modal-actions"><button className="secondary-action" type="button" onClick={closeForm}>Batal</button><button className="primary-action" type="submit" disabled={isSaving || (!editingRequest && eligibleUnits.length === 0)}>{isSaving ? "Menyimpan..." : editingRequest ? "Simpan perubahan" : "Buat permintaan"}</button></div>
                    </form>
                </section>
            </div>}
        </main>
    );
}
