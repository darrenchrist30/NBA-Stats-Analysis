import json
import pandas as pd
from collections import Counter

# Fungsi untuk memuat JSON (sama seperti versi sebelumnya)
def load_json_to_df(filepath, record_path=None, meta=None, encoding='utf-8'):
    try:
        try:
            with open(filepath, 'r', encoding=encoding) as f:
                data = json.load(f)
        except UnicodeDecodeError:
            print(f"Peringatan: Gagal membaca {filepath} dengan encoding {encoding}. Mencoba encoding alternatif...")
            try:
                with open(filepath, 'r', encoding='latin1') as f:
                    data = json.load(f)
                print(f"Berhasil membaca {filepath} dengan encoding latin1.")
            except Exception as e_alt:
                print(f"ERROR: Gagal membaca {filepath} dengan encoding alternatif: {e_alt}")
                return pd.DataFrame()

        if isinstance(data, list) and all(isinstance(item, dict) for item in data):
            df = pd.DataFrame(data)
        elif isinstance(data, dict) and record_path:
            if isinstance(record_path, str):
                df = pd.json_normalize(data.get(record_path, []), meta=meta, record_path=None, errors='ignore')
            elif isinstance(record_path, list):
                df = pd.json_normalize(data, record_path=record_path, meta=meta, errors='ignore')
            else:
                df = pd.DataFrame(data) 
        elif isinstance(data, dict) and not record_path:
             df = pd.DataFrame([data])
        else:
            print(f"Format data tidak didukung untuk {filepath} atau record_path tidak sesuai.")
            return pd.DataFrame()
            
        print(f"Berhasil memuat {filepath}. Jumlah baris: {len(df)}.")
        # if not df.empty: # Komentari bagian ini agar tidak terlalu banyak output awal
            # print(f"Kolom Awal: {', '.join(df.columns)}")
            # print("Beberapa baris awal (sebelum proses):")
            # print(df.head(2))
            # print("\nInfo nilai null awal per kolom (yang ada null):")
            # print(df.isnull().sum()[df.isnull().sum() > 0].sort_values(ascending=False))
            # print("-" * 50)
        return df
            
    except FileNotFoundError:
        print(f"ERROR: File {filepath} tidak ditemukan.")
        return pd.DataFrame()
    except json.JSONDecodeError as e:
        print(f"ERROR: File {filepath} bukan format JSON yang valid. Kesalahan: {e}")
        return pd.DataFrame()
    except Exception as e:
        print(f"ERROR saat memuat atau memproses {filepath}: {e}")
        return pd.DataFrame()

# --- 1. Load Data ---
print("="*10 + " MEMUAT DATA PEMAIN " + "="*10 + "\n")
# Hanya load data pemain karena fokus pada permintaan Anda
df_players_merged = load_json_to_df('players_merged_with_all.json') 

# --- 2. Pre-processing untuk DataFrame Pemain (`df_players_merged`) ---
if not df_players_merged.empty:
    print("\n" + "="*10 + " PRE-PROCESSING PEMAIN " + "="*10)

    df_player_seasons = pd.DataFrame() # Inisialisasi DataFrame kosong

    if 'career_teams' in df_players_merged.columns:
        print("\nMemproses 'career_teams' pemain...")
        # Filter baris dimana 'career_teams' adalah list dan tidak kosong
        df_players_merged_for_explode = df_players_merged[
            df_players_merged['career_teams'].apply(lambda x: isinstance(x, list) and len(x) > 0)
        ].copy() # Gunakan .copy() untuk menghindari SettingWithCopyWarning
        
        if not df_players_merged_for_explode.empty:
            # Explode hanya pada DataFrame yang sudah difilter
            df_player_seasons = df_players_merged_for_explode.explode('career_teams').reset_index(drop=True)
            
            # Normalisasi kolom dari 'career_teams' yang mungkin berupa dict
            # Ambil career_teams sebagai Series dulu
            career_teams_series = df_player_seasons['career_teams']
            career_teams_normalized = pd.json_normalize(career_teams_series) # Normalisasi series ini

            # Gabungkan kembali dengan aman
            df_player_seasons = pd.concat([
                df_player_seasons.drop(['career_teams'], axis=1), 
                career_teams_normalized.set_index(df_player_seasons.index) # Pastikan index cocok
            ], axis=1)
            
            print(f"\nDataFrame pemain per musim ('df_player_seasons') dibuat dengan {len(df_player_seasons)} baris.")
            # print(f"Kolom di 'df_player_seasons' (sebelum penghapusan): {', '.join(df_player_seasons.columns)}")
            # print("\nInfo nilai null di 'df_player_seasons' (sebelum penghapusan):")
            # print(df_player_seasons.isnull().sum()[df_player_seasons.isnull().sum() > 0].sort_values(ascending=False))
            # print("-" * 30)

            # ---- PENGHAPUSAN KOLOM YANG TIDAK BERGUNA ----
            columns_to_drop_player_seasons = [
                'nameGiven', 'note', 'fullGivenName', 'nameSuffix', 'collegeOther', 'nameNick' # Tambahkan nameNick
            ]
            # Pastikan kolom ada sebelum mencoba menghapus
            existing_columns_to_drop = [col for col in columns_to_drop_player_seasons if col in df_player_seasons.columns]
            
            if existing_columns_to_drop:
                df_player_seasons = df_player_seasons.drop(columns=existing_columns_to_drop)
                print(f"\nKolom berikut telah dihapus dari 'df_player_seasons': {', '.join(existing_columns_to_drop)}")
            else:
                print("\nTidak ada kolom yang ditentukan untuk dihapus ditemukan di 'df_player_seasons'.")

            print(f"\nKolom di 'df_player_seasons' (SETELAH penghapusan): {', '.join(df_player_seasons.columns)}")
            print("\nInfo nilai null di 'df_player_seasons' (SETELAH penghapusan, hanya yang > 0):")
            null_counts_after_drop = df_player_seasons.isnull().sum()
            print(null_counts_after_drop[null_counts_after_drop > 0].sort_values(ascending=False))
            print("-" * 50)
            
            print("\nBeberapa baris awal dari tabel 'df_player_seasons' (SETELAH penghapusan kolom):")
            print(df_player_seasons.head()) # Menampilkan 5 baris pertama secara default
            print("-" * 50)
            # ---- SELESAI PENGHAPUSAN KOLOM ----

            # Anda bisa meng-uncomment bagian di bawah ini jika ingin melanjutkan pre-processing lain
            # seperti kalkulasi PPG, dekade, dll., dan melihat hasilnya pada tabel yang sudah bersih.

            # # Lanjutkan dengan pre-processing lainnya pada df_player_seasons
            # numeric_cols_seasons = ['GP', 'points', 'rebounds', 'assists', 'minutes', 'year']
            # existing_numeric_cols = [col for col in numeric_cols_seasons if col in df_player_seasons.columns]
            
            # for col in existing_numeric_cols:
            #     df_player_seasons[col] = pd.to_numeric(df_player_seasons[col], errors='coerce')

            # if existing_numeric_cols:
            #      print("\nStatistik deskriptif untuk kolom numerik utama (per musim pemain):")
            #      print(df_player_seasons[existing_numeric_cols].describe().round(2))
            
            # if 'year' in df_player_seasons.columns and not df_player_seasons['year'].isnull().all():
            #     print(f"\nRange tahun musim pemain: Min: {df_player_seasons['year'].min()}, Max: {df_player_seasons['year'].max()}")

            # df_player_seasons['GP'] = pd.to_numeric(df_player_seasons['GP'], errors='coerce').fillna(0)
            # df_player_seasons_with_gp = df_player_seasons[df_player_seasons['GP'] > 0].copy()

            # if not df_player_seasons_with_gp.empty:
            #     df_player_seasons_with_gp.loc[:, 'PPG'] = (pd.to_numeric(df_player_seasons_with_gp['points'], errors='coerce') / df_player_seasons_with_gp['GP']).fillna(0).round(1)
            #     df_player_seasons_with_gp.loc[:, 'APG'] = (pd.to_numeric(df_player_seasons_with_gp['assists'], errors='coerce') / df_player_seasons_with_gp['GP']).fillna(0).round(1)
            #     df_player_seasons_with_gp.loc[:, 'RPG'] = (pd.to_numeric(df_player_seasons_with_gp['rebounds'], errors='coerce') / df_player_seasons_with_gp['GP']).fillna(0).round(1)
                
            #     print("\nContoh data pemain per musim dengan PPG, APG, RPG (setelah filter GP > 0):")
            #     print(df_player_seasons_with_gp[['playerID', 'year', 'tmID', 'GP', 'points', 'PPG', 'assists', 'APG', 'rebounds', 'RPG']].head())
            # else:
            #     print("\nTidak ada data musim pemain dengan GP > 0 untuk menghitung PPG, APG, RPG.")

            # df_player_seasons_for_decade = df_player_seasons.dropna(subset=['year']).copy() 
            # df_player_seasons_for_decade.loc[:, 'year_int'] = pd.to_numeric(df_player_seasons_for_decade['year'], errors='coerce').fillna(0).astype(int)
            # df_player_seasons_for_decade.loc[:, 'decade_label'] = ((df_player_seasons_for_decade['year_int'] // 10) * 10).astype(str) + "s"
            # print("\nJumlah musim pemain per dekade:")
            # print(df_player_seasons_for_decade['decade_label'].value_counts().sort_index())
        else:
            print("\nTidak ada pemain dengan data 'career_teams' yang valid untuk di-explode.")
    else:
        print("Kolom 'career_teams' tidak ditemukan di DataFrame pemain.")

    # Tidak menampilkan analisis penghargaan lagi agar fokus ke tabel
    # if 'player_awards' in df_players_merged.columns:
    #     ...
else:
    print("DataFrame pemain kosong, pre-processing pemain dilewati.")


print("\n" + "="*10 + " PRE-PROCESSING SELESAI " + "="*10)