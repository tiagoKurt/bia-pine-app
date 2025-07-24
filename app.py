import streamlit as st
import os
import json
from bia import gerar_dicionario_word
from pine import atualizar_planilha

# ===============================
# Carrega credenciais de ambiente
# ===============================
credenciais_str = os.getenv("GOOGLE_CREDENTIALS_JSON")
if credenciais_str:
    try:
        dados_credenciais = json.loads(credenciais_str)
    except Exception as e:
        dados_credenciais = None
        st.error(f"Erro ao carregar credenciais: {e}")
else:
    dados_credenciais = None
    st.warning("Nenhuma credencial encontrada na variável de ambiente GOOGLE_CREDENTIALS_JSON.")

# ===============================
# Configuração geral da página
# ===============================
st.set_page_config(page_title="Ferramentas CKAN", layout="wide")

# ===============================
# Cabeçalho inicial
# ===============================
st.title("FERRAMENTAS CKAN")
st.write("Use o menu lateral para escolher a ação desejada.")

# ===============================
# Menu lateral simples
# ===============================
opcao = st.sidebar.radio(
    "Selecione a ação:",
    ["Gerar Dicionário", "Atualizar Monitoramento"]
)

# ===============================
# Aba: Gerar Dicionário (BIA)
# ===============================
if opcao == "Gerar Dicionário":
    st.header("Gerar Dicionário")
    st.write(
        "Insira o link completo do recurso CKAN que deseja documentar. "
        "Ao finalizar, será gerado um arquivo Word com o dicionário de dados."
    )

    recurso_url = st.text_input("Link do recurso CKAN:")
    template_path = "modelo_bia2_pronto_para_preencher.docx"

    if st.button("Gerar Dicionário"):
        if not recurso_url:
            st.error("Informe o link do recurso CKAN.")
        elif not os.path.exists(template_path):
            st.error("O arquivo de modelo não foi encontrado.")
        else:
            with st.spinner("Gerando o documento Word..."):
                try:
                    caminho_docx = gerar_dicionario_word(recurso_url, template_path)
                    if caminho_docx and os.path.exists(caminho_docx):
                        with open(caminho_docx, "rb") as file:
                            st.success("Dicionário gerado com sucesso.")
                            st.download_button(
                                label="Baixar Documento",
                                data=file,
                                file_name=os.path.basename(caminho_docx),
                                mime="application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                            )
                    else:
                        st.error("Não foi possível gerar o documento.")
                except Exception as e:
                    st.error(f"Ocorreu um erro ao gerar o dicionário: {e}")

# ===============================
# Aba: Atualizar Monitoramento (PINE)
# ===============================
else:
    st.header("Atualizar Monitoramento")
    st.write("Insira o link do portal CKAN que deseja monitorar.")

    portal_url = st.text_input("Link do portal CKAN:")
    verificar_urls = st.checkbox("Verificar URLs dos recursos durante o processamento")
    if st.button("Atualizar Planilha"):
        if not portal_url:
            st.error("Informe o link do portal CKAN.")
        else:
            with st.spinner("Atualizando planilha..."):
                try:
                    sucesso, mensagem = atualizar_planilha(portal_url, verificar_urls)
                    if sucesso:
                        st.success(mensagem)
                    else:
                        st.error(mensagem)
                except Exception as e:
                    st.error(f"Ocorreu um erro ao atualizar a planilha: {e}")
