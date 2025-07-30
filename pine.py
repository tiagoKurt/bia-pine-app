import os
import json
import requests
import pandas as pd
import gspread
from oauth2client.service_account import ServiceAccountCredentials
import streamlit as st

def atualizar_planilha(portal_url: str, verificar_urls: bool):
    SHEET_NAME = "RELATORIO"
    WORKSHEET_NAME = "Página1"

    try:
        # ─────────── Autenticação Google Sheets ───────────
        scope = [
            "https://spreadsheets.google.com/feeds",
            "https://www.googleapis.com/auth/drive"
        ]
        credenciais_str = os.getenv("GOOGLE_CREDENTIALS_JSON")
        if not credenciais_str:
            return False, "Variável de ambiente GOOGLE_CREDENTIALS_JSON não configurada."
        creds_dict = json.loads(credenciais_str)
        creds = ServiceAccountCredentials.from_json_keyfile_dict(creds_dict, scope)
        client = gspread.authorize(creds)
        planilha = client.open(SHEET_NAME)
        aba = planilha.worksheet(WORKSHEET_NAME)

        # ─────────── Buscar todos os datasets + recursos ───────────
        BASE_URL = f"{portal_url.rstrip('/')}/api/3/action"
        session = requests.Session()

        # Paginação para garantir que tragam *todos* os datasets
        start = 0
        rows = 100
        all_packages = []
        while True:
            resp = session.get(
                f"{BASE_URL}/package_search",
                params={"q": "*:*", "rows": rows, "start": start},
                timeout=30
            )
            resp.raise_for_status()
            js = resp.json()
            if not js.get("success", False):
                err = js.get("error", {})
                raise Exception(f"API CKAN retornou erro: {err.get('message', err)}")
            result = js["result"]
            packages = result.get("results", [])
            total = result.get("count", 0)

            all_packages.extend(packages)
            start += rows
            if start >= total:
                break

        # ─────────── Montar tabela unificada (1 linha por recurso) ───────────
        dados = []
        total_datasets = len(all_packages)
        progresso = st.progress(0)

        for idx, pkg in enumerate(all_packages, start=1):
            dataset_id = pkg.get("id", "")
            dataset_title = pkg.get("title", "")
            orgao = pkg.get("organization", {}).get("title", "Não informado")
            data_criacao = pkg.get("metadata_created", "")
            ultima_atualizacao = pkg.get("metadata_modified", "")
            resources = pkg.get("resources", [])

            # Agregar formatos por dataset
            formatos_set = { str(r.get("format", "")).lower() for r in resources if r.get("format") }
            formatos_str = "; ".join(sorted(formatos_set)).upper()

            # Contar erros por dataset (checagem opcional de URLs)
            qtd_erro = 0
            if verificar_urls:
                for res in resources:
                    url = res.get("url", "")
                    try:
                        head = session.head(url, timeout=5)
                        if head.status_code != 200:
                            qtd_erro += 1
                    except Exception:
                        qtd_erro += 1

            # Gerar 1 linha por recurso
            for res in resources:
                dados.append({
                    "ID_Dataset": dataset_id,
                    "Nome_Dataset": dataset_title,
                    "Orgao": orgao,
                    "Data_Criacao_Dataset": data_criacao,
                    "Ultima_Atualizacao_Dataset": ultima_atualizacao,
                    "Formatos_Disponiveis": formatos_str,
                    "Qtde_Erros_Dataset": qtd_erro,
                    "Nome_Recurso": res.get("name", ""),
                    "Formato_Recurso": res.get("format", ""),
                    "URL_Recurso": res.get("url", ""),
                    "Data_Publicacao_Recurso": res.get("created", ""),
                    "Tamanho_Recurso_Bytes": res.get("size", ""),
                    "Descricao_Recurso": res.get("description", "")
                })

            progresso.progress(int(idx / total_datasets * 100))

        # ─────────── Preparar e formatar DataFrame ───────────
        df = pd.DataFrame(dados)

        # Converter datas para formato legível
        for col in ["Data_Criacao_Dataset", "Ultima_Atualizacao_Dataset", "Data_Publicacao_Recurso"]:
            df[col] = pd.to_datetime(df[col], errors="coerce")
            df[col] = df[col].dt.strftime("%d/%m/%Y %H:%M")

        # ─────────── Tratar valores ausentes (remover NaN) ───────────
        df = df.fillna("")  # substitui todos os NaN por string vazia

        # ─────────── Enviar para Google Sheets ───────────
        valores = [df.columns.tolist()] + df.values.tolist()
        aba.clear()
        aba.update(valores)

        progresso.progress(100)
        return True, "Os dados foram extraídos e atualizados com sucesso!"

    except Exception as e:
        return False, f"Erro ao atualizar planilha: {e}"
 extraídos e atualizados com sucesso!"

    except Exception as e:
        return False, f"Erro ao atualizar planilha: {e}"
