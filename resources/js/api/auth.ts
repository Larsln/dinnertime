import { api, data } from './client';
import type { ServerInfo, Session, User } from './types';

export const authApi = {
    server: () => api.get<ServerInfo>('/server'),
    login: (username: string, password: string, deviceName: string) =>
        api.post<Session>('/auth/login', { username, password, device_name: deviceName }),
    loginWithIdToken: (idToken: string, deviceName: string) =>
        api.post<Session>('/auth/oidc', { id_token: idToken, device_name: deviceName }),
    logout: () => api.post<void>('/auth/logout'),
    user: () => data(api.get<{ data: User }>('/auth/user')),
};
