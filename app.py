import streamlit as st
import os
import json
import smtplib
from email.mime.text import MIMEText
from datetime import datetime
from bia import gerar_dicionario_word
from pine import atualizar_planilha

# ===============================
# Função para enviar aviso por e-mail
# ===============================
def enviar_aviso_pine():
    remetente = "bia.generator@gmail.com"  # seu Gmail remetente
    senha_email = os.getenv("EMAIL_PASSWORD")  # senha de app do Gmail configurada no Render
    destinatario = "fernandabas1209@gmail.com"  # e-mail que vai receber o aviso

    agora = datetime.now().strftime("%d/%m/%Y %H:%M:%S")
    corpo = f"O sistema PINE foi atualizado em {agora}."

    msg = MIMEText(corpo)
    msg["Subject"] = "Aviso: Atualização no PINE"
    msg["From"] = remetente
    msg["To"] = destinatario

    try:
        with smtplib.SMTP_SSL("smtp.gmail.com", 465) as servidor:
            servidor.login(remetente, senha_email)
            servidor.sendmail(remetente, destinatario, msg.as_string())
    except Exception as e:
        st.warning(f"Não foi possível enviar o e-mail de aviso: {e}")

# ===============================
# Controle de autenticação com session_state
# ===============================
senha_correta = os.getenv("APP_PASSWORD")

if "autenticado" not in st.session_state:
    st.session_state.autenticado = False

if not st.session_state.autenticado:
    st.set_page_config(page_title="Ferramentas CKAN – Login", layout="wide")
    st.title("Acesso Restrito – Ferramentas CKAN")
    senha_digitada = st.text_input("Digite a senha:", type="password")
    if senha_digitada:
        if senha_digitada == senha_correta:
            st.session_state.autenticado = True
            st.experimental_rerun()
        else:
            st.error("Senha incorreta.")
    st.stop()

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
st.title("FERRAMENTAS CKAN")

# ===============================
# Menu lateral
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
            sucesso, mensagem = atualizar_planilha(portal_url, verificar_urls)
            if sucesso:
                st.success(mensagem)
                # Verifica variável de ambiente para saber se envia e-mail
                enviar_aviso = os.getenv("ENVIAR_AVISO_PINE", "false").lower() == "true"
                if enviar_aviso:
                    enviar_aviso_pine()
            else:
                st.error(mensagem)

