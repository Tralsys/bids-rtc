import { getAnalytics } from "firebase/analytics";
import { FirebaseOptions, initializeApp } from "firebase/app";
import {
	connectAuthEmulator,
	getAuth,
	GithubAuthProvider,
	GoogleAuthProvider,
} from "firebase/auth";
import { IS_DOCKER_DEBUG, IS_LOCAL_DEBUG } from "../constants";

const firebaseConfig: FirebaseOptions = {
	apiKey: import.meta.env.VITE_FIREBASE_API_KEY,
	authDomain: import.meta.env.VITE_FIREBASE_AUTH_DOMAIN,
	projectId: import.meta.env.VITE_FIREBASE_PROJECT_ID,
	storageBucket: import.meta.env.VITE_FIREBASE_STORAGE_BUCKET,
	messagingSenderId: import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID,
	appId: import.meta.env.VITE_FIREBASE_APP_ID,
	measurementId: import.meta.env.VITE_MEASUREMENT_ID,
};

function initFirebaseServices() {
	const app = initializeApp(firebaseConfig);
	const analytics = getAnalytics(app);
	const auth = getAuth(app);

	if (IS_LOCAL_DEBUG) {
		connectAuthEmulator(auth, "http://localhost:9099");
	} else if (IS_DOCKER_DEBUG) {
		connectAuthEmulator(
			auth,
			`${window.location.protocol}//${window.location.host}:9099`
		);
	}

	const googleAuthProvider = new GoogleAuthProvider();
	const githubAuthProvider = new GithubAuthProvider();

	return {
		analytics,
		auth,
		googleAuthProvider,
		githubAuthProvider,
	};
}

export const { analytics, auth, googleAuthProvider, githubAuthProvider } =
	initFirebaseServices();
