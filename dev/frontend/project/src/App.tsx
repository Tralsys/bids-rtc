import ShowCurrentData from "./components/ShowCurrentData";
import SignInUp from "./components/SignInUp";
import WebRTCContext from "./components/WebRTCContext";
import { useUserId } from "./firebase/FirebaseHook";
import { Role, ROLE } from "./webrtc/ConnectionManager";

const query = new URLSearchParams(window.location.search);
const role: Role = (() => {
	const role = query.get("role");
	if (Object.values(ROLE).includes(role as Role)) {
		return role as Role;
	} else {
		return ROLE.SUBSCRIBER;
	}
})();
console.log("Role: ", role);

function App() {
	const userId = useUserId();
	return (
		<>
			<h1>BIDS-WebRTC Demo App</h1>
			{userId && (
				<WebRTCContext role={role}>
					<ShowCurrentData userId={userId} />
				</WebRTCContext>
			)}
			<SignInUp />
		</>
	);
}

export default App;
