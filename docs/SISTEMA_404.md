# Sistema de Redirecionamento 404

## Visão Geral

Este documento descreve como o sistema de redirecionamento para páginas inexistentes (404) foi implementado no projeto.

## Componentes do Sistema

### 1. Configuração do Apache (.htaccess)

O arquivo `.htaccess` na raiz do projeto contém:

```apache
# Configuração de páginas de erro
ErrorDocument 404 /404.html

# Redirecionamento principal
<IfModule mod_rewrite.c>
    RewriteEngine On
    
    # Roteamento principal
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    # Excluir 404.html do roteamento para evitar loop
    RewriteCond %{REQUEST_URI} !^/404\.html$
    # Excluir router.php do roteamento para evitar loop
    RewriteCond %{REQUEST_URI} !^/router\.php$
    RewriteRule ^(.*)$ router.php [L]
</IfModule>
```

**Funcionalidades:**
- `ErrorDocument 404 /404.html`: Define a página personalizada de erro 404
- Reescreve URLs que não correspondem a arquivos físicos para o `router.php`
- Exclui `404.html` e `router.php` do roteamento para evitar loops infinitos

### 2. Router Principal (router.php)

O arquivo `router.php` na raiz do projeto é responsável por:

- Receber todas as requisições que não correspondem a arquivos físicos
- Verificar se a URL corresponde a uma rota válida do sistema
- Servir arquivos estáticos quando apropriado
- Redirecionar para a página 404 quando necessário

**Rotas Definidas:**
- Páginas públicas: `index`, `login`, `register`, etc.
- Páginas protegidas: `dashboard`, `nova_simulacao`, etc.
- Páginas administrativas: `admin`, `admin_settings`, etc.

### 3. Manipulador de Erros (ErrorHandler.php)

A classe `ErrorHandler` em `src/Utils/ErrorHandler.php` fornece:

**Métodos Principais:**
- `initialize()`: Configura manipuladores de erro personalizados
- `show404Page()`: Exibe a página 404 personalizada
- `handleError()`: Manipula erros PHP
- `handleException()`: Manipula exceções não capturadas
- `handleShutdown()`: Manipula erros fatais

### 4. Página 404 Personalizada (404.html)

O arquivo `404.html` na raiz do projeto contém:

- Design responsivo com Bootstrap
- Interface moderna com animações CSS
- Botões de navegação (Página Inicial, Voltar, WhatsApp)
- Sugestões de ação para o usuário
- Otimizada para SEO com meta tags apropriadas

## Como Funciona

### Fluxo de Redirecionamento

1. **Usuário acessa URL inexistente** (ex: `/pagina-que-nao-existe`)

2. **Apache verifica arquivo físico**
   - Se existe: serve o arquivo diretamente
   - Se não existe: continua para próximo passo

3. **Apache aplica regras do .htaccess**
   - Verifica se não é `404.html` ou `router.php`
   - Reescreve a URL para `router.php`

4. **Router.php processa a requisição**
   - Verifica rotas válidas definidas
   - Tenta localizar arquivos estáticos
   - Se nada for encontrado: chama `ErrorHandler::show404Page()`

5. **ErrorHandler exibe página 404**
   - Define cabeçalho HTTP 404
   - Inclui o arquivo `404.html`
   - Se `404.html` não existir: exibe HTML básico de fallback

### Tipos de Erro Capturados

- **URLs inexistentes**: `/pagina-falsa`, `/admin/inexistente`
- **Arquivos não encontrados**: `/script.js`, `/imagem.png`
- **Erros PHP**: Exceptions, erros fatais, parse errors
- **Acessos protegidos**: Tentativas de acesso a arquivos sensíveis

## Configuração e Personalização

### Adicionando Novas Rotas

Para adicionar uma nova rota válida, edite o array `$routes` em `router.php`:

```php
$routes = [
    'nova-pagina' => 'public/nova_pagina.php',
    // ... outras rotas
];
```

### Personalizando a Página 404

- Edite o arquivo `404.html` para alterar o design
- Modifique o método `getBasic404Html()` no `ErrorHandler.php` para alterar o fallback

### Configurações de Ambiente

O sistema detecta automaticamente o ambiente:
- **Desenvolvimento**: `localhost` - logs detalhados habilitados
- **Produção**: outros domínios - logs reduzidos para segurança

## Segurança

### Proteções Implementadas

1. **Arquivos Sensíveis Bloqueados**
   - `/config/`, `/src/`, `/vendor/`, `/docs/`
   - Arquivos `.env`, `.log`, `.sql`, `.bak`

2. **Prevenção de Directory Traversal**
   - Bloqueio de tentativas `../` em URLs

3. **Headers de Segurança**
   - `X-Frame-Options`, `X-Content-Type-Options`
   - `Content-Security-Policy`

4. **Rate Limiting**
   - Proteção contra hotlinking de imagens
   - Cache apropriado para recursos estáticos

## Monitoramento e Logs

### Em Desenvolvimento
- Logs detalhados de tentativas de acesso
- Stack traces de erros PHP
- Informações de debug no console

### Em Produção
- Logs reduzidos para performance
- Apenas erros críticos são registrados
- Informações sensíveis são omitidas

## Testes

Execute o arquivo `test_404.php` para verificar se o sistema está funcionando:

1. Acesse `/test_404.php` no navegador
2. Clique nos links de teste fornecidos
3. Verifique se todos redirecionam para a página 404
4. **Importante**: Delete o arquivo após os testes

## Manutenção

### Verificações Regulares

1. **Logs de Erro**: Monitore logs para identificar URLs problemáticas
2. **Performance**: Verifique se o sistema não está causando overhead
3. **Atualizações**: Mantenha as regras de rota atualizadas

### Resolução de Problemas

**Problema**: Loop infinito de redirecionamento
- **Solução**: Verifique se `404.html` e `router.php` estão excluídos das regras de reescrita

**Problema**: Página 404 não é exibida
- **Solução**: Verifique permissões do arquivo `404.html` e configuração do Apache

**Problema**: Arquivos estáticos não carregam
- **Solução**: Verifique se o router está servindo arquivos estáticos corretamente

## Compatibilidade

- **Apache 2.4+** com mod_rewrite habilitado
- **PHP 7.4+**
- **Bootstrap 5.3+** (para o design da página 404)

## Changelog

- **v1.0**: Implementação inicial do sistema 404
  - Router centralizado
  - Página 404 personalizada  
  - Manipulador de erros PHP
  - Configuração Apache/.htaccess
