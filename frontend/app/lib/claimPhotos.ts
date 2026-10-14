const DATABASE_NAME = "garansitech-local-photos";
const STORE_NAME = "claim-photos";
const DATABASE_VERSION = 1;

type StoredClaimPhoto = {
  claimCode: string;
  file: Blob;
  fileName: string;
  contentType: string;
};

function isStoredClaimPhoto(value: unknown): value is StoredClaimPhoto {
  return (
    typeof value === "object" &&
    value !== null &&
    "claimCode" in value &&
    typeof value.claimCode === "string" &&
    "file" in value &&
    value.file instanceof Blob &&
    "fileName" in value &&
    typeof value.fileName === "string" &&
    "contentType" in value &&
    typeof value.contentType === "string"
  );
}

function openPhotoDatabase(): Promise<IDBDatabase> {
  if (typeof indexedDB === "undefined") {
    return Promise.reject(new Error("Penyimpanan foto browser tidak tersedia."));
  }

  return new Promise((resolve, reject) => {
    const request = indexedDB.open(DATABASE_NAME, DATABASE_VERSION);

    request.onupgradeneeded = () => {
      if (!request.result.objectStoreNames.contains(STORE_NAME)) {
        request.result.createObjectStore(STORE_NAME, { keyPath: "claimCode" });
      }
    };
    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error || new Error("Database foto gagal dibuka."));
    request.onblocked = () => reject(new Error("Database foto sedang dipakai tab lain."));
  });
}

export async function saveClaimPhoto(claimCode: string, file: File): Promise<void> {
  const database = await openPhotoDatabase();

  return new Promise((resolve, reject) => {
    const transaction = database.transaction(STORE_NAME, "readwrite");
    transaction.objectStore(STORE_NAME).put({
      claimCode,
      file,
      fileName: file.name,
      contentType: file.type,
    } satisfies StoredClaimPhoto);
    transaction.oncomplete = () => {
      database.close();
      resolve();
    };
    transaction.onerror = () => {
      database.close();
      reject(transaction.error || new Error("Foto gagal disimpan di browser ini."));
    };
    transaction.onabort = () => {
      database.close();
      reject(transaction.error || new Error("Penyimpanan foto dibatalkan."));
    };
  });
}

export async function getClaimPhoto(claimCode: string): Promise<StoredClaimPhoto | null> {
  const database = await openPhotoDatabase();

  return new Promise((resolve, reject) => {
    const transaction = database.transaction(STORE_NAME, "readonly");
    const request = transaction.objectStore(STORE_NAME).get(claimCode);
    let photo: StoredClaimPhoto | null = null;

    request.onsuccess = () => {
      const result: unknown = request.result;
      if (isStoredClaimPhoto(result)) photo = result;
    };
    transaction.oncomplete = () => {
      database.close();
      resolve(photo);
    };
    transaction.onerror = () => {
      database.close();
      reject(transaction.error || new Error("Foto klaim gagal dibaca."));
    };
    transaction.onabort = () => {
      database.close();
      reject(transaction.error || new Error("Pembacaan foto klaim dibatalkan."));
    };
  });
}
