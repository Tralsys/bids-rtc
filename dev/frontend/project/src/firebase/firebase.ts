import { getAnalytics } from "firebase/analytics";
import { FirebaseOptions, initializeApp } from "firebase/app";
import {
	connectAuthEmulator,
	getAuth,
	GoogleAuthProvider,
} from "firebase/auth";
import { IS_LOCAL_DEBUG } from "../constants";

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
	}

	const googleAuthProvider = new GoogleAuthProvider();

	return {
		analytics,
		auth,
		googleAuthProvider,
	};
}

export const { analytics, auth, googleAuthProvider } = initFirebaseServices();
