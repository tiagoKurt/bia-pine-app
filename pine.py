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
        # Autenticação Google Sheets
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

        # Requisição à API CKAN
        BASE_URL = f"{portal_url.rstrip('/')}/api/3/action"
        resp_list = requests.get(f"{BASE_URL}/package_list", timeout=30)
        resp_list.raise_for_status()
        lista_datasets = resp_list.json().get("result", [])

        dados_finais = []
        total = len(lista_datasets)
        progresso = st.progress(0, text="Lendo CKAN...")
        contador = 0

        for dataset_id in lista_datasets:
            try:
                resp = requests.get(f"{BASE_URL}/package_show?id={dataset_id}", timeout=30)
                resp.raise_for_status()
                info = resp.json()["result"]

                # Informações gerais do dataset
                id_base = info.get("id", "")
                nome_base = info.get("title", "")
                nome_tecnico = info.get("name", "")
                orgao_nome = info.get("organization", {}).get("title", "Não informado")
                orgao_id = info.get("organization", {}).get("name", "")
                descricao = info.get("notes", "")
                privado = "Sim" if info.get("private", False) else "Não"
                tipo = info.get("type", "")
                criado_em = info.get("metadata_created", "")
                modificado_em = info.get("metadata_modified", "")
                tags = "; ".join([tag.get("name", "") for tag in info.get("tags", [])])
                grupos = "; ".join([grp.get("name", "") for grp in info.get("groups", [])])
                extras = "; ".join([f"{e.get('key')}: {e.get('value')}" for e in info.get("extras", [])])

                # Recursos
                resources = info.get("resources", [])
                qtd_total = len(resources)
                qtd_csv = qtd_xlsx = qtd_pdf = qtd_json = qtd_erro = 0
                formatos = set()
                urls = []

                for res in resources:
                    formato = str(res.get("format", "")).lower()
                    formatos.add(formato.upper())
                    url = res.get("url", "")
                    urls.append(url)

                    if formato == "csv":
                        qtd_csv += 1
                    elif formato == "xlsx":
                        qtd_xlsx += 1
                    elif formato == "pdf":
                        qtd_pdf += 1
                    elif formato == "json":
                        qtd_json += 1

                if verificar_urls:
                    for url in urls:
                        try:
                            check = requests.head(url, timeout=5)
                            if check.status_code != 200:
                                qtd_erro += 1
                        except:
                            qtd_erro += 1

                dados_finais.append({
                    "ID": id_base,
                    "Nome_Tecnico": nome_tecnico,
                    "Nome_da_Base": nome_base,
                    "Descrição": descricao,
                    "Tipo": tipo,
                    "Privado?": privado,
                    "Órgão (ID)": orgao_id,
                    "Órgão (Nome)": orgao_nome,
                    "Tags": tags,
                    "Grupos": grupos,
                    "Extras": extras,
                    "Data_Criacao": criado_em,
                    "Ultima_Atualizacao": modificado_em,
                    "Quantidade_de_Recursos": qtd_total,
                    "Quantidade_CSV": qtd_csv,
                    "Quantidade_XLSX": qtd_xlsx,
                    "Quantidade_PDF": qtd_pdf,
                    "Quantidade_JSON": qtd_json,
                    "Formatos_Encontrados": "; ".join(sorted(formatos)),
                    "Quantidade_ErroLeitura": qtd_erro
                })

            except Exception as e:
                print(f"⚠️ Erro ao processar dataset {dataset_id}: {e}")

            contador += 1
            progresso.progress(int((contador / total) * 100), text=f"Processando {contador}/{total}...")

        # Enviar para o Google Sheets
        planilha = client.open(SHEET_NAME)
        aba = planilha.worksheet(WORKSHEET_NAME)
        df_novos = pd.DataFrame(dados_finais).fillna("")

        if not df_novos.empty:
            df_novos['Data_Criacao'] = pd.to_datetime(df_novos['Data_Criacao'], errors='coerce').dt.strftime('%d/%m/%Y')
            df_novos['Ultima_Atualizacao'] = pd.to_datetime(df_novos['Ultima_Atualizacao'], errors='coerce').dt.strftime('%d/%m/%Y')

        dados = [df_novos.columns.tolist()] + df_novos.values.tolist()
        aba.clear()
        aba.update(dados)

        progresso.progress(100, text="Concluído!")
        return True, "Os dados foram extraídos com sucesso!"

    except Exception as e:
        return False, f"Erro ao atualizar planilha: {e}"

