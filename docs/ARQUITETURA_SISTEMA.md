# Documentação de Arquitetura do Sistema

## Visão Geral

Este documento descreve a arquitetura do sistema de Simulação Simples de INSS para obras, mapeando as relações entre telas, banco de dados e regras de negócio principais. Ele serve como referência viva para manutenção e evolução do sistema.

---

## Telas Principais e Relação com o Banco

| Tela                     | Arquivo PHP                  | Tabela(s) Relacionada(s) |
|------------------------- |-----------------------------|--------------------------|
| Dashboard                | public/dashboard.php         | simulacoes               |
| Nova Simulação           | public/nova_simulacao.php    | simulacoes, vau          |
| Minhas Simulações        | public/minhas_simulacoes.php | simulacoes               |
| Editar Simulação         | public/editar_simulacao.php  | simulacoes, vau          |
| Relatório da Simulação   | public/relatorio_simulacao.php| simulacoes, vau         |
| Exportar Simulações CSV  | public/exportar_simulacoes_csv.php | simulacoes        |
| Login/Logout             | public/login.php, logout.php | users                    |

---

## Estrutura das Tabelas Principais

### simulacoes
- id
- user_id
- responsavel
- regime_tributario
- nome
- destinacao
- tipo_obra
- uf
- area
- municipio
- endereco
- valor_inss
- vau_id
- criado_em
- atualizado_em

### vau
- id
- uf
- destinacao
- tipo_obra
- mes
- ano
- valor

### users
- id
- nome
- email
- senha
- ...

---

## Fluxo Principal de Cálculo (Simulação)

1. **Usuário preenche dados da obra** em Nova Simulação.
2. **Sistema valida os dados** (`CalculoINSS::validarDados`).
3. **Busca o VAU** vigente conforme UF, destinação e data da simulação (`CalculoINSS::buscarVau`).
   - Se for edição, o cálculo do INSS é sempre refeito com os dados atuais:
     - Se o usuário optar por manter a data, o campo criado_em é salvo com o valor original (data da simulação não muda).
     - Se optar por atualizar, o campo criado_em recebe a data atual.
   - O valor do INSS é sempre salvo atualizado.
4. **Calcula área equivalente, COD, RMT e INSS** conforme regras da IN RFB Nº 2.021/2021.
5. **Salva a simulação** na tabela `simulacoes`.
6. **Relatório** exibe todos os detalhes, inclusive VAU e data de referência.

---

## Pontos Críticos e Observações

- **VAU por Data:** O cálculo do VAU considera a data da simulação. Alterações retroativas no VAU não afetam simulações antigas.
- **Edição de Simulação:** Ao editar, o usuário pode escolher manter a data original (mantém VAU antigo) ou atualizar para a data atual (usa VAU novo).
- **Regras de Negócio:**
  - Área equivalente e fatores seguem a IN 2.021/2021.
  - Fator social só para Pessoa Física.
  - Alíquotas variam conforme tipo de responsável e regime tributário.
- **Remoção de Simulação Intermediária:** O sistema está focado apenas em Simulação Simples.
- **Layout:** Todas as telas usam CSS global, sem estilos inline.
- **Manutenção:** Sempre que regras de cálculo, banco ou telas forem alteradas, atualizar este documento.

---

## Para Desenvolvedores

- **Adicionar novas regras:** Atualize este documento e os métodos em `src/Utils/CalculoINSS.php`.
- **Novas telas:** Relacione aqui e descreva a ligação com tabelas e regras.
- **Dúvidas sobre fluxo:** Consulte este documento antes de alterar regras de negócio.

---

*Última atualização: [preencher data ao atualizar]* 