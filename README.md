# Calculadora INSS Obra Legal ®

Sistema web para cálculo de INSS em obras, desenvolvido em PHP com arquitetura MVC.

## 🚀 Instalação

### Pré-requisitos
- PHP 7.4 ou superior
- MySQL 5.7 ou superior
- Servidor web (Apache/Nginx)
- Composer

### 1. Clone o repositório
```bash
git clone https://github.com/seu-usuario/calculadora-inss-obra.git
cd calculadora-inss-obra
```

### 2. Instale as dependências
```bash
composer install
```

### 3. Configure o banco de dados
- Crie um banco de dados MySQL
- Execute o script `criar_tabela_planejamento_tributario.sql`
- Configure as credenciais em `config/config.php`

### 4. Configure as configurações locais
```bash
cp config/local_settings.example.php config/local_settings.php
```
Edite o arquivo `config/local_settings.php` com suas informações.

### 5. Configure o servidor web
- Aponte o DocumentRoot para a pasta `public/`
- Certifique-se de que o mod_rewrite está habilitado
- Configure as permissões adequadas para a pasta `uploads/`

## 🔧 Configuração do Git (Para projetos existentes)

Se você já tem um projeto funcionando e quer adicionar controle de versão:

### 1. Inicialize o Git (se ainda não foi feito)
```bash
git init
```

### 2. Adicione os arquivos
```bash
git add .
```

### 3. Faça o primeiro commit
```bash
git commit -m "Commit inicial: Sistema Calculadora INSS Obra Legal"
```

### 4. Adicione um repositório remoto (opcional)
```bash
git remote add origin https://github.com/seu-usuario/calculadora-inss-obra.git
git branch -M main
git push -u origin main
```

## 📁 Estrutura do Projeto

```
├── config/                 # Configurações do sistema
├── public/                 # Arquivos públicos (DocumentRoot)
│   ├── assets/            # CSS, JS e imagens
│   ├── uploads/           # Uploads de usuários
│   └── index.php          # Ponto de entrada
├── src/                   # Código fonte
│   ├── Controllers/       # Controladores MVC
│   ├── Models/            # Modelos de dados
│   └── Utils/             # Utilitários
├── vendor/                # Dependências do Composer
└── .htaccess             # Configurações do Apache
```

## 🎨 Personalização

### Logo e Favicon
- Coloque sua logo em `public/uploads/logos/logo.png`
- Coloque seu favicon em `public/uploads/favicons/favicon.png`
- Configure as cores e fontes em `config/local_settings.php`

### Cores do Tema
Edite o arquivo `config/local_settings.php` na seção `theme.colors`:

```php
'colors' => [
    'primary' => '#2c5aa0',      // Cor principal
    'secondary' => '#f8f9fa',    // Cor secundária
    'sidebar_bg' => '#343a40',   // Cor do fundo da sidebar
    // ... outras cores
]
```

## 🔒 Segurança

- O sistema inclui proteções contra XSS, CSRF e SQL Injection
- Headers de segurança configurados automaticamente
- Proteção contra directory traversal
- Validação de entrada em todos os formulários

## 📝 Logs

- Logs de erro: `logs/error.log`
- Logs de sessão: `logs/session.log`
- Logs de upload: `logs/upload.log`

## 🚨 Solução de Problemas

### Logo não aparece
- Verifique se o arquivo existe em `public/uploads/logos/`
- Confirme as permissões do arquivo (644)
- Verifique se não há barras duplas na URL (problema comum em domínios raiz)

### Erro de permissão
```bash
chmod -R 755 public/uploads/
chmod -R 644 public/uploads/logos/*
chmod -R 644 public/uploads/favicons/*
```

### Problema de roteamento
- Verifique se o mod_rewrite está habilitado
- Confirme se o .htaccess está sendo lido
- Teste com `RewriteLog` habilitado

## 📞 Suporte

- Email: suporte@sistema-obralegal.com.br
- Documentação: Consulte os arquivos na pasta `docs/`

## 📄 Licença

Este projeto é proprietário da Hevcon WEB. Todos os direitos reservados.

---

**Desenvolvido por:** Hevcon WEB  
**Versão:** 2.0  
**Última atualização:** Janeiro 2025 