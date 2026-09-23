"use client";

import { FormEvent, useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import {
  createWarranty,
  getProductUnits,
  getToken,
  getWarranties,
  ProductUnit,
  Warranty,
} from "../lib/api";

const statusLabels: Record<string, string> = {
  active: "Aktif",
  expiring: "Segera berakhir",
  expired: "Berakhir",
};

export default function WarrantiesPage() {
  const router = useRouter();
  const [warranties, setWarranties] = useState<Warranty[]>([]);
  const [units, setUnits] = useState<ProductUnit[]>([]);
  const [query, setQuery] = useState("");
  const [status, setStatus] = useState("");
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
    Promise.all([getWarranties(), getProductUnits()])
      .then(([loadedWarranties, loadedUnits]) => {
        setWarranties(loadedWarranties);
        setUnits(loadedUnits);
      })
      .catch((requestError: unknown) => {
        setError(requestError instanceof Error ? requestError.message : "Data garansi tidak dapat dimuat.");
      })
      .finally(() => setIsLoading(false));
  }, [router]);

  async function refreshList(nextQuery = query, nextStatus = status) {
    try {
      setError("");
      setWarranties(await getWarranties(nextQuery, nextStatus));
    } catch (requestError) {
      setError(requestError instanceof Error ? requestError.message : "Data garansi tidak dapat dimuat.");
    }
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setIsSaving(true);
    setFormError("");
    const formData = new FormData(event.currentTarget);
    try {
      await createWarranty({
        product_unit_id: Number(formData.get("product_unit_id")),
        start_date: String(formData.get("start_date")),
        end_date: String(formData.get("end_date")),
        notes: String(formData.get("notes") || ""),
      });
      setIsFormOpen(false);
      event.currentTarget.reset();
      await refreshList();
    } catch (requestError) {
      setFormError(requestError instanceof Error ? requestError.message : "Garansi tidak dapat disimpan.");
    } finally {
      setIsSaving(false);
    }
  }

  return (
    <main className="claims-page">
      <header className="page-header">
        <Link className="back-link" href="/">← Dashboard</Link>
        <div className="page-header-content">
          <div>
            <p className="dashboard-kicker">MASTER GARANSI</p>
            <h1>Garansi produk</h1>
            <p>Registrasikan unit dan pantau masa berlaku garansi pelanggan.</p>
          </div>
          <button className="primary-action" type="button" onClick={() => setIsFormOpen(true)}>+ Daftarkan garansi</button>
        </div>
      </header>

      <section className="claims-page-content">
        {error && <p className="form-error" role="alert">{error}</p>}
        <section className="claims-list-panel">
          <div className="claims-list-toolbar">
            <div><h2>Semua garansi</h2><span>{warranties.length} data ditemukan</span></div>
            <div className="claim-filters">
              <label className="search-field"><span>⌕</span><input value={query} onChange={(event) => setQuery(event.target.value)} onKeyDown={(event) => event.key === "Enter" && refreshList()} placeholder="Cari kode, serial, pelanggan..." /></label>
              <select value={status} onChange={(event) => { setStatus(event.target.value); refreshList(query, event.target.value); }} aria-label="Filter status garansi">
                <option value="">Semua status</option>
                <option value="active">Aktif</option>
                <option value="expiring">Segera berakhir</option>
                <option value="expired">Berakhir</option>
              </select>
            </div>
          </div>
          <div className="claims-table-wrap">
            {isLoading ? <div className="empty-state"><strong>Memuat data garansi...</strong></div> : warranties.length === 0 ? <div className="empty-state"><strong>Garansi tidak ditemukan</strong><span>Coba ubah kata kunci atau daftarkan garansi baru.</span></div> : (
              <table className="claims-table claims-page-table">
                <thead><tr><th>Kode garansi</th><th>Pelanggan</th><th>Produk / serial</th><th>Periode</th><th>Status</th></tr></thead>
                <tbody>{warranties.map((warranty) => <tr key={warranty.id}>
                  <td><strong>{warranty.warranty_code}</strong><small>ID #{warranty.id.toString().padStart(3, "0")}</small></td>
                  <td>{warranty.product_unit.customer?.name || "-"}</td>
                  <td><strong>{warranty.product_unit.product?.name || "-"}</strong><small>{warranty.product_unit.serial_number}</small></td>
                  <td>{new Date(warranty.start_date).toLocaleDateString("id-ID")} - {new Date(warranty.end_date).toLocaleDateString("id-ID")}</td>
                  <td><span className={`status-badge status-${warranty.status}`}>{statusLabels[warranty.status]}</span></td>
                </tr>)}</tbody>
              </table>
            )}
          </div>
        </section>
      </section>

      {isFormOpen && <div className="modal-backdrop" role="presentation" onClick={() => setIsFormOpen(false)}>
        <section className="claim-modal" role="dialog" aria-modal="true" aria-labelledby="warranty-modal-title" onClick={(event) => event.stopPropagation()}>
          <button className="modal-close" type="button" onClick={() => setIsFormOpen(false)} aria-label="Tutup">×</button>
          <p className="dashboard-kicker">GARANSI BARU</p>
          <h2 id="warranty-modal-title">Daftarkan garansi</h2>
          {formError && <p className="form-error" role="alert">{formError}</p>}
          <form onSubmit={handleSubmit}>
            <label>Unit produk<select name="product_unit_id" required defaultValue=""><option value="" disabled>Pilih unit produk</option>{units.map((unit) => <option key={unit.id} value={unit.id}>{unit.serial_number} — {unit.product?.name || "Produk"} / {unit.customer?.name || "Tanpa pelanggan"}</option>)}</select></label>
            <label>Tanggal mulai<input name="start_date" type="date" required /></label>
            <label>Tanggal berakhir<input name="end_date" type="date" required /></label>
            <label>Catatan<textarea name="notes" rows={3} placeholder="Catatan tambahan (opsional)" /></label>
            <div className="modal-actions"><button className="secondary-action" type="button" onClick={() => setIsFormOpen(false)}>Batal</button><button className="primary-action" type="submit" disabled={isSaving}>{isSaving ? "Menyimpan..." : "Simpan garansi"}</button></div>
          </form>
        </section>
      </div>}
    </main>
  );
}
