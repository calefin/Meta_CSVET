import google.generativeai as genai

# COLE SUA API KEY AQUI
API_KEY = "AIzaSyAQv4FDtHRWJnREp7OCFo3xIaY6kUHHPcE"

genai.configure(api_key=API_KEY)

print("Consultando modelos disponíveis para sua chave...")
try:
    for m in genai.list_models():
        if 'generateContent' in m.supported_generation_methods:
            print(f"✅ DISPONÍVEL: {m.name}")
except Exception as e:
    print(f"Erro de conexão: {e}")