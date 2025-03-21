import { lazy, StrictMode, Suspense } from "react";
import { createRoot } from "react-dom/client";
import i18n, { changeLanguage } from "i18next";
import I18nextBrowserLanguageDetector from "i18next-browser-languagedetector";
import I18NextHttpBackend from "i18next-http-backend";
import { I18N_LANGUAGES, I18N_LANGUAGES_ARRAY } from "./i18n.ts";
import { initReactI18next } from "react-i18next";

import type { I18N_LANGUAGE_TYPE } from "./i18n.ts";
import { ThemeProvider } from "@emotion/react";
import theme from "./theme.ts";
import { CssBaseline } from "@mui/material";
import ModalProgress from "./components/ModalProgress.tsx";

const rootNode = document.getElementById("root");
if (rootNode == null) {
	const message = "ERROR: rootNode is null";
	const errorMsgElement = document.createElement("p");
	errorMsgElement.textContent = message;
	document.body.appendChild(errorMsgElement);
	throw new Error(message);
}

i18n
	.use(I18nextBrowserLanguageDetector)
	.use(I18NextHttpBackend)
	.use(initReactI18next)
	.init({
		fallbackLng: I18N_LANGUAGES.Japanese,
		debug: true,
		interpolation: {
			escapeValue: false,
		},
		backend: {
			loadPath: `${window.location.origin}/i18n/{{lng}}.json`,
		},
	})
	.then(() => {
		console.log("i18n initialized");

		if (
			I18N_LANGUAGES_ARRAY.indexOf(i18n.language as I18N_LANGUAGE_TYPE) === -1
		) {
			console.log(
				"i18n.language was not in I18N_LANGUAGE_TYPE",
				i18n.language,
				I18N_LANGUAGES_ARRAY
			);
			const language = i18n.language.split("-")[0];
			if (I18N_LANGUAGES_ARRAY.indexOf(language as I18N_LANGUAGE_TYPE) !== -1) {
				console.log("changeLanguage (language)", language);
				changeLanguage(language as I18N_LANGUAGE_TYPE);
			} else {
				console.log("changeLanguage (default)", I18N_LANGUAGES.English);
				changeLanguage(I18N_LANGUAGES.English);
			}
		}
	});

const AppLazy = lazy(() => import("./App.tsx"));

createRoot(rootNode).render(
	<StrictMode>
		<ThemeProvider theme={theme}>
			<CssBaseline />
			<Suspense fallback={<ModalProgress />}>
				<AppLazy />
			</Suspense>
		</ThemeProvider>
	</StrictMode>
);
