#!/bin/bash
set -e

# Configurações de diretórios
ANDROID_DIR="/home/calefi/Android"
SDK_DIR="$ANDROID_DIR/Sdk"
JDK_DIR="$ANDROID_DIR/Jdk"

echo "=== 1. Criando diretórios ==="
mkdir -p "$SDK_DIR/cmdline-tools"
mkdir -p "$JDK_DIR"

echo "=== 2. Baixando JDK 17 Portable (Eclipse Temurin) ==="
JDK_TAR="$JDK_DIR/temurin_jdk17.tar.gz"
if [ ! -d "$JDK_DIR/jdk-17.0.11+9" ]; then
    echo "Baixando JDK 17..."
    wget -q --show-progress -O "$JDK_TAR" "https://github.com/adoptium/temurin17-binaries/releases/download/jdk-17.0.11%2B9/OpenJDK17U-jdk_x64_linux_hotspot_17.0.11_9.tar.gz"
    echo "Extraindo JDK 17..."
    tar -xzf "$JDK_TAR" -C "$JDK_DIR"
    rm "$JDK_TAR"
else
    echo "JDK 17 já baixado e extraído."
fi

# Definindo variáveis de ambiente locais para execução das ferramentas
export JAVA_HOME="$JDK_DIR/jdk-17.0.11+9"
export PATH="$JAVA_HOME/bin:$PATH"

echo "=== 3. Baixando Android Command Line Tools (oficial do Google) ==="
TEMP_ZIP=$(mktemp)
wget -q --show-progress -O "$TEMP_ZIP" "https://dl.google.com/android/repository/commandlinetools-linux-11076708_latest.zip"

echo "=== 4. Extraindo Command Line Tools ==="
unzip -q "$TEMP_ZIP" -d "$SDK_DIR/cmdline-tools"
rm "$TEMP_ZIP"

# Organiza os arquivos para o padrão 'latest' exigido pelo sdkmanager
rm -rf "$SDK_DIR/cmdline-tools/latest"
mkdir -p "$SDK_DIR/cmdline-tools/latest"
mv "$SDK_DIR/cmdline-tools/cmdline-tools/"* "$SDK_DIR/cmdline-tools/latest/"
rmdir "$SDK_DIR/cmdline-tools/cmdline-tools"

# Adiciona o diretório do sdkmanager no PATH local
export PATH="$SDK_DIR/cmdline-tools/latest/bin:$SDK_DIR/platform-tools:$PATH"

echo "=== 5. Aceitando as Licenças do Android SDK ==="
yes | sdkmanager --licenses --sdk_root="$SDK_DIR"

echo "=== 6. Instalando Platform Tools, Build Tools e API 34 ==="
sdkmanager --sdk_root="$SDK_DIR" "platform-tools" "build-tools;34.0.0" "platforms;android-34"

echo "=== 7. Configurando o Flutter ==="
flutter config --android-sdk "$SDK_DIR"
flutter config --jdk-dir "$JAVA_HOME"

echo "=== 8. Verificando ambiente com Flutter Doctor ==="
flutter doctor -v

echo "=== SDK e JDK configurados com sucesso! ==="
