"use client";

import Link from "next/link";
import { FormEvent, useState } from "react";
import { useRouter } from "next/navigation";

type Mode = "login" | "register";

type ApiResponse = {
  token?: string;
  message?: string;
  errors?: Record<string, string[]>;
};

const API_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api";

export default function AuthForm({ mode }: { mode: Mode }) {
  const isLogin = mode === "login";
  const router = useRouter();
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState("");

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError("");
    setIsLoading(true);

    const formData = new FormData(event.currentTarget);
    const payload = Object.fromEntries(formData.entries());

    try {
      const response = await fetch(`${API_URL}/auth/${mode}`, {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify(payload),
      });
      const data: ApiResponse = await response.json();

      if (!response.ok || !data.token) {
        const validationMessage = data.errors ? Object.values(data.errors).flat()[0] : undefined;
        throw new Error(validationMessage || data.message || "Terjadi kesalahan. Silakan coba lagi.");
      }

      localStorage.setItem("garansitech_token", data.token);
      router.push("/");
    } catch (requestError) {
      setError(requestError instanceof Error ? requestError.message : "Tidak dapat terhubung ke server.");
    } finally {
      setIsLoading(false);
    }
  }

  return (
    <main className="auth-page">
      <section className="auth-hero" aria-hidden="true">
        <div className="auth-brand"><span>G</span> GaransiTech</div>
        <div className="hero-copy"><p>GARANSI LEBIH TERKENDALI</p><h1>Semua proses garansi, dalam satu tempat.</h1><span>Kelola produk, pantau klaim, dan berikan pengalaman terbaik untuk pelanggan.</span></div>
        <div className="hero-card"><i>✓</i><div><strong>Proses klaim transparan</strong><small>Pelanggan selalu mendapat pembaruan status.</small></div></div>
      </section>

      <section className="auth-content">
        <div className="auth-box">
          <Link className="mobile-brand" href="/"><span>G</span> GaransiTech</Link>
          <p className="auth-kicker">{isLogin ? "SELAMAT DATANG KEMBALI" : "BUAT AKUN BARU"}</p>
          <h2>{isLogin ? "Masuk ke akun Anda" : "Daftarkan akun Anda"}</h2>
          <p className="auth-description">{isLogin ? "Masukkan detail akun untuk melanjutkan ke dashboard." : "Lengkapi data berikut untuk mulai mengelola garansi."}</p>

          <form onSubmit={handleSubmit} noValidate>
            {!isLogin && <label>Nama lengkap<input name="name" type="text" placeholder="Masukkan nama lengkap" autoComplete="name" required /></label>}
            <label>Email<input name="email" type="email" placeholder="nama@email.com" autoComplete="email" required /></label>
            <label>Kata sandi<input name="password" type="password" placeholder={isLogin ? "Masukkan kata sandi" : "Minimal 8 karakter"} autoComplete={isLogin ? "current-password" : "new-password"} minLength={8} required /></label>
            {!isLogin && <label>Konfirmasi kata sandi<input name="password_confirmation" type="password" placeholder="Ulangi kata sandi" autoComplete="new-password" minLength={8} required /></label>}
            {isLogin && <div className="form-options"><label className="remember"><input type="checkbox" /> Ingat saya</label><a href="#reset">Lupa kata sandi?</a></div>}
            {error && <p className="form-error" role="alert">{error}</p>}
            <button className="auth-submit" type="submit" disabled={isLoading}>{isLoading ? "Memproses..." : isLogin ? "Masuk" : "Daftar"}</button>
          </form>
          <p className="auth-switch">{isLogin ? "Belum punya akun?" : "Sudah punya akun?"} <Link href={isLogin ? "/register" : "/login"}>{isLogin ? "Daftar sekarang" : "Masuk"}</Link></p>
        </div>
      </section>
    </main>
  );
}
