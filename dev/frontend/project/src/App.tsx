import {
	Box,
	Button,
	CircularProgress,
	Divider,
	Stack,
	Typography,
} from "@mui/material";
import { useUserId } from "./firebase/FirebaseHook";
import {
	ComponentType,
	FC,
	lazy,
	Suspense,
	useCallback,
	useState,
} from "react";
import { CLIENT_REGISTER_PARAMS } from "./constants";
import ModalProgress from "./components/ModalProgress";

function App() {
	const [currentPage, setCurrentPage] = useState<PageType | null>(
		CLIENT_REGISTER_PARAMS == null ? null : PAGE_TYPE["/clients"]
	);
	const userId = useUserId();
	const onClickBack = useCallback(() => {
		setCurrentPage(null);
	}, []);
	return (
		<>
			<h1>BIDS-WebRTC Demo App</h1>
			<Stack direction="row" spacing={2} alignItems="center" sx={{ mb: 2 }}>
				<Typography variant="body1">
					あなたのUserID: {userId ?? "未ログイン"}
				</Typography>
				<Suspense fallback={<ModalProgress open={userId == null} />}>
					<SignInUpLazy />
				</Suspense>
			</Stack>
			<Divider />
			<Box>
				{userId &&
					(currentPage == null ? (
						<Stack
							direction="row"
							spacing={2}
							alignItems="center"
							sx={{ my: 2 }}
						>
							{Object.keys(PAGE_TYPE).map((key) => (
								<Button
									key={key}
									variant="outlined"
									onClick={() => setCurrentPage(key as PageType)}
								>
									{key}
								</Button>
							))}
						</Stack>
					) : (
						<Suspense
							fallback={
								<Box
									sx={{
										width: "100%",
										flex: 1,
										display: "flex",
										alignItems: "center",
										justifyContent: "center",
									}}
								>
									<CircularProgress size="4rem" />
								</Box>
							}
						>
							<Page type={currentPage} onClickBack={onClickBack} />
						</Suspense>
					))}
			</Box>
		</>
	);
}

export default App;

const SignInUpLazy = lazy(() => import("./components/SignInUp"));

type PageProps = {
	type: PageType;
	onClickBack: () => void;
};
const Page: FC<PageProps> = ({ type, onClickBack }) => {
	const Component = PAGE_MAP[type];
	return <Component onClickBack={onClickBack} />;
};

const PAGE_MAP = {
	"/clients": lazy(() => import("./components/ClientManagement")),
	"/webrtc": lazy(() => import("./components/ShowCurrentData")),
} as const satisfies Record<string, ComponentType<{ onClickBack: () => void }>>;
type PageType = keyof typeof PAGE_MAP;
const PAGE_TYPE = {
	"/clients": "/clients",
	"/webrtc": "/webrtc",
} as const satisfies Record<string, PageType>;
