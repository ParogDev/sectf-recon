import { Target } from 'lucide-react';

export function NoTargetPage() {
    return (
        <div className="mx-auto mt-10 max-w-lg rounded-2xl border border-dashed border-zinc-300 p-8 text-center dark:border-zinc-700" data-test="empty-state">
            <Target className="mx-auto size-8 text-zinc-400" />
            <h1 className="mt-3 text-lg font-semibold">No target yet</h1>
            <p className="mt-2 text-sm text-zinc-500">
                Targets are created from Claude Code. In a session connected to this server, ask Claude to run the{' '}
                <code className="rounded bg-zinc-100 px-1 py-0.5 font-mono text-xs dark:bg-zinc-800">create_target</code> MCP tool with the company name; the board appears here
                the moment it exists.
            </p>
            <p className="mt-2 text-xs text-zinc-500">Looking at someone else's target? Ask its owner to share it with you.</p>
        </div>
    );
}
