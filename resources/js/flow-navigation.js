export function resolveConnectionEdge(edges, {
    nodeId,
    handleId = null,
    direction = 'outgoing',
}) {
    if (!Array.isArray(edges) || !nodeId) return null;

    const candidates = edges.filter((edge) => direction === 'incoming'
        ? edge.target === nodeId
        : edge.source === nodeId);

    if (direction === 'incoming') {
        return candidates.find((edge) => !handleId || edge.targetHandle === handleId) || null;
    }

    if (handleId) {
        return candidates.find((edge) => edge.sourceHandle === handleId
            || edge.data?.optionKey === handleId) || null;
    }

    return candidates.find((edge) => !edge.sourceHandle && !edge.data?.optionKey)
        || candidates[0]
        || null;
}

export function connectionDestination(edge, direction = 'outgoing') {
    if (!edge) return null;

    return direction === 'incoming' ? edge.source : edge.target;
}
