#!/bin/bash

# Script d'installation de l'authentification pour Job Visualization
# Usage: ./install_auth.sh [project_root_directory]

set -e

# Couleurs pour les messages
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Fonction pour afficher les messages
print_message() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_step() {
    echo -e "${BLUE}[STEP]${NC} $1"
}

# Vérifier les arguments
PROJECT_ROOT=${1:-$(pwd)}

if [ ! -d "$PROJECT_ROOT" ]; then
    print_error "Le répertoire du projet '$PROJECT_ROOT' n'existe pas."
    exit 1
fi

print_message "Installation de l'authentification dans: $PROJECT_ROOT"

# Créer les répertoires nécessaires
print_step "1. Création des répertoires..."
mkdir -p "$PROJECT_ROOT/src/Controller"
mkdir -p "$PROJECT_ROOT/src/Service"
mkdir -p "$PROJECT_ROOT/src/EventListener"
mkdir -p "$PROJECT_ROOT/src/Twig"
mkdir -p "$PROJECT_ROOT/templates/security"
mkdir -p "$PROJECT_ROOT/config/packages"

# Copier les fichiers
print_step "2. Copie des fichiers..."

# Contrôleurs
cp "src/Controller/SecurityController_updated.php" "$PROJECT_ROOT/src/Controller/SecurityController.php"
print_message "✓ SecurityController copié"

# Services
cp "src/Service/AuthenticationService.php" "$PROJECT_ROOT/src/Service/"
print_message "✓ AuthenticationService copié"

# Event Listeners
cp "src/EventListener/AuthenticationListener.php" "$PROJECT_ROOT/src/EventListener/"
print_message "✓ AuthenticationListener copié"

# Twig Extensions
cp "src/Twig/AuthExtension.php" "$PROJECT_ROOT/src/Twig/"
print_message "✓ AuthExtension copié"

# Templates
cp "templates/security/login.html.twig" "$PROJECT_ROOT/templates/security/"
print_message "✓ Template de connexion copié"

# Sauvegarder le base.html.twig existant s'il existe
if [ -f "$PROJECT_ROOT/templates/base.html.twig" ]; then
    cp "$PROJECT_ROOT/templates/base.html.twig" "$PROJECT_ROOT/templates/base.html.twig.backup"
    print_warning "Sauvegarde de base.html.twig créée: base.html.twig.backup"
fi

cp "templates/base.html.twig" "$PROJECT_ROOT/templates/"
print_message "✓ Template de base mis à jour"

# Configuration
cp "config/packages/security.yaml" "$PROJECT_ROOT/config/packages/"
print_message "✓ Configuration de sécurité copiée"

cp "config/packages/framework.yaml" "$PROJECT_ROOT/config/packages/"
print_message "✓ Configuration du framework copiée"

# Sauvegarder les routes existantes s'il y en a
if [ -f "$PROJECT_ROOT/config/routes.yaml" ]; then
    cp "$PROJECT_ROOT/config/routes.yaml" "$PROJECT_ROOT/config/routes.yaml.backup"
    print_warning "Sauvegarde de routes.yaml créée: routes.yaml.backup"
fi

cp "config/routes.yaml" "$PROJECT_ROOT/config/"
print_message "✓ Configuration des routes copiée"

# Services
if [ -f "$PROJECT_ROOT/config/services.yaml" ]; then
    cp "$PROJECT_ROOT/config/services.yaml" "$PROJECT_ROOT/config/services.yaml.backup"
    print_warning "Sauvegarde de services.yaml créée: services.yaml.backup"
fi

cp "config/services.yaml" "$PROJECT_ROOT/config/"
print_message "✓ Configuration des services copiée"

# Fichier d'environnement
if [ ! -f "$PROJECT_ROOT/.env" ]; then
    cp ".env" "$PROJECT_ROOT/"
    print_message "✓ Fichier .env créé"
else
    print_warning "Le fichier .env existe déjà. Vérifiez les variables nécessaires."
fi

print_step "3. Génération de la clé secrète..."
# Générer une clé secrète aléatoire
SECRET_KEY=$(openssl rand -hex 32)
if [ -f "$PROJECT_ROOT/.env" ]; then
    sed -i "s/your-secret-key-here-change-this-in-production/$SECRET_KEY/" "$PROJECT_ROOT/.env"
    print_message "✓ Clé secrète générée et configurée"
fi

print_step "4. Configuration des permissions..."
# Configurer les permissions
chmod +x "$PROJECT_ROOT/bin/console" 2>/dev/null || true
chmod -R 755 "$PROJECT_ROOT/var" 2>/dev/null || true

print_step "5. Vérification de l'installation..."

# Vérifier que tous les fichiers sont en place
FILES_TO_CHECK=(
    "src/Controller/SecurityController.php"
    "src/Service/AuthenticationService.php"
    "src/EventListener/AuthenticationListener.php"
    "src/Twig/AuthExtension.php"
    "templates/security/login.html.twig"
    "templates/base.html.twig"
    "config/packages/security.yaml"
    "config/packages/framework.yaml"
    "config/routes.yaml"
    "config/services.yaml"
)

ALL_GOOD=true
for file in "${FILES_TO_CHECK[@]}"; do
    if [ ! -f "$PROJECT_ROOT/$file" ]; then
        print_error "Fichier manquant: $file"
        ALL_GOOD=false
    fi
done

if [ "$ALL_GOOD" = true ]; then
    print_message "✓ Tous les fichiers sont en place"
else
    print_error "Certains fichiers sont manquants. Vérifiez l'installation."
    exit 1
fi

print_step "6. Instructions finales..."

echo ""
print_message "🎉 Installation terminée avec succès!"
echo ""
print_message "IDENTIFIANTS DE CONNEXION:"
echo "  Nom d'utilisateur: Zied Enneifer"
echo "  Mot de passe: 123Wimbee@"
echo ""
print_message "PROCHAINES ÉTAPES:"
echo "1. Vérifiez votre configuration Symfony"
echo "2. Exécutez: php bin/console cache:clear"
echo "3. Démarrez votre serveur: symfony server:start"
echo "4. Accédez à: http://localhost:8000/login"
echo ""
print_warning "SÉCURITÉ:"
echo "- Changez les identifiants en production"
echo "- Configurez HTTPS en production"
echo "- Vérifiez les permissions des fichiers"
echo ""
print_message "Pour plus d'informations, consultez la documentation fournie."

