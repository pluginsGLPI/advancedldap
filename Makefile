include ../../PluginsMakefile.mk

lint-js: ## Run ESLint on JS files
	@$(PLUGIN) ../../node_modules/.bin/eslint .
.PHONY: lint-js
