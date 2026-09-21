import { type Action, action } from 'easy-peasy';

export type SubuserPermission =
    | 'websocket.connect'
    | 'control.console'
    | 'control.start'
    | 'control.stop'
    | 'control.restart'
    | 'user.create'
    | 'user.read'
    | 'user.update'
    | 'user.delete'
    | 'file.create'
    | 'file.read'
    | 'file.read-content'
    | 'file.update'
    | 'file.delete'
    | 'file.archive'
    | 'file.sftp'
    | 'backup.create'
    | 'backup.read'
    | 'backup.delete'
    | 'backup.download'
    | 'backup.restore'
    | 'allocation.read'
    | 'allocation.create'
    | 'allocation.update'
    | 'allocation.delete'
    | 'startup.read'
    | 'startup.update'
    | 'startup.command'
    | 'startup.docker-image'
    | 'startup.software'
    | 'database.create'
    | 'database.read'
    | 'database.update'
    | 'database.delete'
    | 'database.view_password'
    | 'schedule.create'
    | 'schedule.read'
    | 'schedule.update'
    | 'schedule.delete'
    | 'settings.rename'
    | 'settings.reinstall'
    | 'activity.read'
    | 'mod.version'
    | 'mod.loader'
    | 'mod.download'
    | 'mod.resolver';

export interface Subuser {
    uuid: string;
    username: string;
    email: string;
    image: string;
    twoFactorEnabled: boolean;
    createdAt: Date;
    permissions: SubuserPermission[];

    can(permission: SubuserPermission): boolean;
}

export interface ServerSubuserStore {
    data: Subuser[];
    setSubusers: Action<ServerSubuserStore, Subuser[]>;
    appendSubuser: Action<ServerSubuserStore, Subuser>;
    removeSubuser: Action<ServerSubuserStore, string>;
}

const subusers: ServerSubuserStore = {
    data: [],

    setSubusers: action((state, payload) => {
        state.data = payload;
    }),

    appendSubuser: action((state, payload) => {
        let matched = false;
        state.data = [
            ...state.data
                .map((user) => {
                    if (user.uuid === payload.uuid) {
                        matched = true;

                        return payload;
                    }

                    return user;
                })
                .concat(matched ? [] : [payload]),
        ];
    }),

    removeSubuser: action((state, payload) => {
        state.data = [...state.data.filter((user) => user.uuid !== payload)];
    }),
};

export default subusers;
