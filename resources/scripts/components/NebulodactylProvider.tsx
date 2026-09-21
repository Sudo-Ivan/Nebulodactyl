// Provides necessary information for components to function properly
// million-ignore
const NebulodactylProvider = ({ children }) => {
    return (
        <div
            data-nebulodactyl-provider=''
            data-nebulodactyl-version={import.meta.env.VITE_NEBULODACTYL_VERSION}
            data-nebulodactyl-build={import.meta.env.VITE_NEBULODACTYL_BUILD_NUMBER}
            data-nebulodactyl-commit-hash={import.meta.env.VITE_COMMIT_HASH}
            style={{
                display: 'contents',
            }}
        >
            {children}
        </div>
    );
};

export default NebulodactylProvider;
