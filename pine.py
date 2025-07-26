import requests
import pandas as pd
import gspread
from oauth2client.service_account import ServiceAccountCredentials
import os
import json
import streamlit as st

def atualizar_planilha(portal_url: str, verificar_urls: bool):
    SHEET_NAME = "RELATORIO"
    WORKSHEET_NAME = "Página1"

    try:
        # Autenticação
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

        # 📡 Buscar lista de datasets
        BASE_URL = f"{portal_url.rstrip('/')}/api/3/action"
        resp_list = requests.get(f"{BASE_URL}/package_list", timeout=30)
        resp_list.raise_for_status()
        lista_datasets = resp_list.json().get("result", [])

        dados_finais = []

        total = len(lista_datasets)
        progresso = st.progress(0)
        contador = 0

        # 📌 Loop nos datasets
        for dataset_id in lista_datasets:
            try:
                resp = requests.get(f"{BASE_URL}/package_show?id={dataset_id}", timeout=30)
                resp.raise_for_status()
                info = resp.json()["result"]

                id_base = info.get("id", dataset_id)  # ✅ pega o ID único da base
                nome_base = info.get("title", dataset_id)
                orgao = info.get("organization", {}).get("title", "Não informado")
                ultima_atualizacao = info.get("metadata_modified", "")
                data_criacao = info.get("metadata_created", "")
                resources = info.get("resources", [])

                qtd_total = len(resources)
                qtd_csv = 0
                qtd_xlsx = 0
                qtd_pdf = 0
                qtd_json = 0
                qtd_erro = 0

                for res in resources:
                    formato = str(res.get("format", "")).lower()
                    if formato == "csv":
                        qtd_csv += 1
                    elif formato == "xlsx":
                        qtd_xlsx += 1
                    elif formato == "pdf":
                        qtd_pdf += 1
                    elif formato == "json":
                        qtd_json += 1

                if verificar_urls:
                    for res in resources:
                        url = res.get("url", "")
                        try:
                            check = requests.head(url, timeout=5)
                            if check.status_code != 200:
                                qtd_erro += 1
                        except:
                            qtd_erro += 1

                # ✅ Agora adicionamos o ID na primeira coluna
                # ✅ E deixamos o total de recursos na última
                dados_finais.append({
                    "ID_da_Base": id_base,                # COLUNA A
                    "Nome_da_Base": nome_base,
                    "Orgao": orgao,
                    "Ultima_Atualizacao": ultima_atualizacao,
                    "Data_Criacao": data_criacao,
                    "Quantidade_CSV": qtd_csv,
                    "Quantidade_XLSX": qtd_xlsx,
                    "Quantidade_PDF": qtd_pdf,
                    "Quantidade_JSON": qtd_json,
                    "Quantidade_ErroLeitura": qtd_erro,
                    "Quantidade_de_Recursos": qtd_total   # ÚLTIMA COLUNA
                })

            except Exception as e:
                print(f"⚠️ Erro ao processar dataset {dataset_id}: {e}")

            contador += 1
            porcentagem = int((contador / total) * 100)
            progresso.progress(porcentagem)

        # ✨ Atualizar planilha no Google Sheets
        planilha = client.open(SHEET_NAME)
        aba = planilha.worksheet(WORKSHEET_NAME)

        df_novos = pd.DataFrame(dados_finais)

        if not df_novos.empty:
            df_novos['Ultima_Atualizacao'] = pd.to_datetime(
                df_novos['Ultima_Atualizacao'], errors='coerce'
            ).dt.strftime('%d/%m/%Y')
            df_novos['Data_Criacao'] = pd.to_datetime(
                df_novos['Data_Criacao'], errors='coerce'
            ).dt.strftime('%d/%m/%Y')

        # Atualiza com os novos dados
        dados = [df_novos.columns.tolist()] + df_novos.values.tolist()
        aba.clear()
        aba.update(dados)

        progresso.progress(100)
        return True, "Os dados foram extraídos e atualizados com sucesso!"

    except Exception as e:
        return False, f"Erro ao atualizar planilha: {e}"
