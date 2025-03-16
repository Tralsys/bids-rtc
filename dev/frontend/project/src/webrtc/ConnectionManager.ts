import { SDPAnswerInfo, SDPOfferInfo } from "@tralsys/bids-rtc-signaling-api";
import { sdpExchangeApi, xClientId } from "../api";

export const DATA_CHANNEL_LABEL = "bids-rtc-data-main";

const SDP_ID_UNSET = "(unset)";

export const ROLE = {
	PROVIDER: "provider",
	SUBSCRIBER: "subscriber",
} as const;
export type Role = (typeof ROLE)[keyof typeof ROLE];
type SdpIdRef = { current: string };

export type DataGotEventArgs = {
	// clientId: string;
	dataChannel: RTCDataChannel;
	data: ArrayBuffer;
};
export type DataChannelStateChangedEventArgs = {
	// clientId: string;
	dataChannel: RTCDataChannel;
};

type CreateRTCPeerConnectionResult = {
	peerConnection: RTCPeerConnection;
	sdpIdRef: SdpIdRef;
};

type WindowObj = {
	connManager?: RTCConnectionManager;
};
const _window = window as unknown as WindowObj;

const sleepMsAsync = (ms: number) =>
	new Promise((resolve) => setTimeout(resolve, ms));

function toBase64(str: string) {
	const uint8Array = new TextEncoder().encode(str);
	return btoa(String.fromCharCode(...uint8Array));
}
function fromBase64(base64: string) {
	const str = atob(base64);
	const uint8Array = new Uint8Array(str.length);
	for (let i = 0; i < str.length; ++i) {
		uint8Array[i] = str.charCodeAt(i);
	}
	return new TextDecoder().decode(uint8Array);
}

export class RTCConnectionManager {
	private _abortSignal: AbortController = new AbortController();
	private _establishedConnectionMap: Record<string, RTCPeerConnection> = {};
	public _dataChannelMap: Record<string, Record<string, RTCDataChannel>> = {};
	private _connectionStateMap: Record<string, string> = {};
	private _peerClientIdMap: Map<RTCPeerConnection, string> = new Map();
	private _role: Role;

	//#region DataGotEvent
	private _dataGotListeners: ((e: DataGotEventArgs) => void)[] = [];
	public addDataGotEventListener(listener: (e: DataGotEventArgs) => void) {
		this._dataGotListeners.push(listener);
	}
	public removeDataGotEventListener(listener: (e: DataGotEventArgs) => void) {
		const index = this._dataGotListeners.indexOf(listener);
		if (0 <= index) {
			this._dataGotListeners.splice(index, 1);
		}
	}

	private dispatchDataGotEvent(e: DataGotEventArgs) {
		for (const listener of this._dataGotListeners) {
			listener(e);
		}
	}
	//#endregion

	//#region DataChannelOpenEvent
	private _dataChannelOpenListeners: ((
		e: DataChannelStateChangedEventArgs
	) => void)[] = [];
	public addDataChannelOpenEventListener(
		listener: (e: DataChannelStateChangedEventArgs) => void
	) {
		this._dataChannelOpenListeners.push(listener);
	}
	public removeDataChannelOpenEventListener(
		listener: (e: DataChannelStateChangedEventArgs) => void
	) {
		const index = this._dataChannelOpenListeners.indexOf(listener);
		if (0 <= index) {
			this._dataChannelOpenListeners.splice(index, 1);
		}
	}

	private dispatchDataChannelOpenEvent(e: DataChannelStateChangedEventArgs) {
		for (const listener of this._dataChannelOpenListeners) {
			listener(e);
		}
	}
	//#endregion

	//#region DataChannelClosedEvent
	private _dataChannelClosedListeners: ((
		e: DataChannelStateChangedEventArgs
	) => void)[] = [];
	public addDataChannelClosedEventListener(
		listener: (e: DataChannelStateChangedEventArgs) => void
	) {
		this._dataChannelClosedListeners.push(listener);
	}
	public removeDataChannelClosedEventListener(
		listener: (e: DataChannelStateChangedEventArgs) => void
	) {
		const index = this._dataChannelClosedListeners.indexOf(listener);
		if (0 <= index) {
			this._dataChannelClosedListeners.splice(index, 1);
		}
	}

	private dispatchDataChannelClosedEvent(e: DataChannelStateChangedEventArgs) {
		for (const listener of this._dataChannelClosedListeners) {
			listener(e);
		}
	}
	//#endregion

	constructor(role: Role) {
		this._role = role;
		_window.connManager = this;
		this._registerOffer();
	}

	public async Dispose() {
		console.log("Dispose RTCConnectionManager");
		this._abortSignal.abort();
		if (_window.connManager === this) {
			delete _window.connManager;
		}
		for (const conn of Object.values(this._establishedConnectionMap)) {
			conn.close();
		}
		this._establishedConnectionMap = {};
		this._dataChannelMap = {};
		this._connectionStateMap = {};
	}

	private _createRTCPeerConnection(
		sdpId: string = SDP_ID_UNSET
	): CreateRTCPeerConnectionResult {
		const role = this._role;
		const sdpIdRef: SdpIdRef = { current: sdpId };
		const peerConnection = new RTCPeerConnection();

		const onAborted = () => {
			peerConnection.close();
		};
		this._abortSignal.signal.addEventListener("abort", onAborted, {
			once: true,
		});

		peerConnection.addEventListener("connectionstatechange", () => {
			this._onConnectionStateChange(sdpIdRef, peerConnection);

			if (peerConnection.connectionState === "connected") {
				this._establishedConnectionMap[sdpIdRef.current] = peerConnection;
				this._abortSignal.signal.removeEventListener("abort", onAborted);
			}
		});

		peerConnection.ondatachannel = (e) => {
			console.log(
				`DataChannel received: ${e.channel.label}[${role}]@${sdpIdRef.current}`
			);
			this._setupDataChannel(e.channel, sdpIdRef);
			this._dataChannelMap[sdpIdRef.current] ??= {};
			this._dataChannelMap[sdpIdRef.current][e.channel.label] = e.channel;
			if (e.channel.readyState === "open") {
				this.dispatchDataChannelOpenEvent({
					dataChannel: e.channel,
				});
			}
		};

		return { peerConnection, sdpIdRef };
	}

	private async _registerOffer() {
		const role = this._role;
		console.log(`Registering offer for ${role}`);
		const { peerConnection, sdpIdRef } = this._createRTCPeerConnection();
		const dataChannel = peerConnection.createDataChannel(DATA_CHANNEL_LABEL);
		this._setupDataChannel(dataChannel, sdpIdRef);

		const iceCandidateComplete = new Promise<string | undefined>((resolve) => {
			peerConnection.onicecandidate = (e) => {
				if (!e.candidate) {
					resolve(peerConnection.localDescription?.sdp);
				}
			};
		});

		peerConnection.addEventListener("connectionstatechange", () => {
			if (peerConnection.connectionState === "connected") {
				this._registerOffer();
			}
		});

		const offer = await peerConnection.createOffer();
		if (!offer.sdp) {
			throw new Error("Failed to create offer");
		}
		await peerConnection.setLocalDescription(offer);
		console.log(`Created offer: ${sdpIdRef.current}`, offer);

		const offerSDP = await iceCandidateComplete;
		if (offerSDP == null) {
			console.error("Failed to get offer SDP");
			peerConnection.close();
			return;
		}

		const { registeredOffer, receivedOffers } =
			await sdpExchangeApi.registerOffer({
				xClientId,
				postSDPOfferInfoRequestBody: {
					role,
					offer: toBase64(offerSDP),
					establishedClients: Array.from(this._peerClientIdMap.values()),
				},
			});
		if (registeredOffer == null) {
			console.error("Failed to register offer");
			throw new Error("Failed to register offer");
		}
		sdpIdRef.current = registeredOffer.sdpId;
		console.log(`Offer Registered: ${sdpIdRef.current}`);
		this._connectionStateMap[sdpIdRef.current] = peerConnection.connectionState;

		if (receivedOffers != null) {
			const answerList = await Promise.all(
				receivedOffers.map((receivedOffer) =>
					this._onOfferReceived(receivedOffer)
				)
			);
			console.log("answerList", answerList);
			await sdpExchangeApi.registerAnswer({
				xClientId,
				sDPAnswerInfo: receivedOffers
					.map((offer, i): SDPAnswerInfo | undefined => {
						const answer = answerList[i];
						if (!answer) {
							return undefined;
						}
						return {
							sdpId: offer.sdpId,
							answer: toBase64(answer),
							answerClientId: xClientId,
						};
					})
					.filter((v) => v != null),
			});
		}

		try {
			while (!this._abortSignal.signal.aborted) {
				const answerRes = await sdpExchangeApi.getAnswerRaw(
					{
						xClientId,
						sdpId: sdpIdRef.current,
					},
					{ signal: this._abortSignal.signal }
				);
				if (this._abortSignal.signal.aborted) {
					peerConnection.close();
					return;
				} else if (answerRes.raw.status === 204) {
					await sleepMsAsync(1000);
					continue;
				} else if (answerRes.raw.status !== 200) {
					console.error("Failed to get answer", answerRes.raw);
					peerConnection.close();
					return;
				}
				const answerInfo = await answerRes.value();
				if (answerInfo == null) {
					console.error("Failed to get answer");
					throw new Error("Failed to get answer");
				}
				this._peerClientIdMap.set(peerConnection, answerInfo.answerClientId);
				const answerSDP = new RTCSessionDescription({
					type: "answer",
					sdp: fromBase64(answerInfo.answer),
				});
				await peerConnection.setRemoteDescription(answerSDP);
				break;
			}
		} catch (e) {
			console.error("Failed to get answer", e);
			peerConnection.close();
		}
	}

	private async _onOfferReceived(
		offerInfo: SDPOfferInfo
	): Promise<string | undefined> {
		console.log(`Received offer: ${offerInfo.sdpId}`, offerInfo);
		const offer = new RTCSessionDescription({
			type: "offer",
			sdp: fromBase64(offerInfo.offer),
		});
		const { peerConnection } = this._createRTCPeerConnection(offerInfo.sdpId);
		this._peerClientIdMap.set(peerConnection, offerInfo.offerClientId);
		const iceCandidateComplete = new Promise<string | undefined>((resolve) => {
			peerConnection.onicecandidate = (e) => {
				if (!e.candidate) {
					resolve(peerConnection.localDescription?.sdp);
				}
			};
		});
		await peerConnection.setRemoteDescription(offer);
		const answer = await peerConnection.createAnswer();
		await peerConnection.setLocalDescription(answer);
		return await iceCandidateComplete;
	}

	private async _setupDataChannel(dc: RTCDataChannel, sdpIdRef: SdpIdRef) {
		const role = this._role;
		dc.onerror = (e) => this._onDataChannelError(sdpIdRef.current, e);
		dc.onopen = (e: Event) => {
			const event = e as RTCDataChannelEvent;
			const dc = event.channel;
			if (!dc) {
				return;
			}
			const sdpId = sdpIdRef.current;
			this._dataChannelMap[sdpId] ??= {};
			this._dataChannelMap[sdpId][dc.label] = dc;
			this.dispatchDataChannelOpenEvent({
				dataChannel: dc,
			});
			console.log(`DataChannel opened for ${sdpId}[${role}]@${dc.label}`);
			dc.send(`Hello, world! from ${sdpId}[${role}]@${dc.label}`);
		};
		dc.onmessage = (e: MessageEvent) => {
			if (e.data instanceof ArrayBuffer) {
				this.dispatchDataGotEvent({
					dataChannel: dc,
					data: e.data,
				});
			}
		};
		dc.onclose = () => {
			console.log(
				`DataChannel closed for ${sdpIdRef.current}[${role}]@${dc.label}`
			);
			if (this._dataChannelMap[sdpIdRef.current]) {
				delete this._dataChannelMap[sdpIdRef.current][dc.label];
			}
			this.dispatchDataChannelClosedEvent({
				dataChannel: dc,
			});
		};
	}

	private async _onDataChannelError(connId: string, e: RTCErrorEvent) {
		console.error(`DataChannel error for ${connId}`, e);
		this._establishedConnectionMap[connId].close();
		delete this._establishedConnectionMap[connId];
	}

	private _onConnectionStateChange(
		sdpIdRef: SdpIdRef,
		peerConnection: RTCPeerConnection
	) {
		console.log(
			`Connection state for ${sdpIdRef.current}: ${peerConnection.connectionState}`
		);
		if (sdpIdRef.current === SDP_ID_UNSET) {
			return;
		}

		this._connectionStateMap[sdpIdRef.current] = peerConnection.connectionState;

		if (peerConnection.connectionState === "closed") {
			peerConnection.close();
			delete this._establishedConnectionMap[sdpIdRef.current];
			delete this._dataChannelMap[sdpIdRef.current];
		}
	}
}
