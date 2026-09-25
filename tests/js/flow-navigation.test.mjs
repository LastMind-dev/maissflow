import test from 'node:test';
import assert from 'node:assert/strict';
import {
    connectionDestination,
    resolveConnectionEdge,
} from '../../resources/js/flow-navigation.js';

const edges = [
    { id: 'trigger-main', source: 'trigger', target: 'main' },
    {
        id: 'main-nfe',
        source: 'main',
        target: 'nfe',
        sourceHandle: 'nfe',
        data: { optionKey: 'nfe' },
    },
    {
        id: 'main-support',
        source: 'main',
        target: 'support',
        data: { optionKey: 'support' },
    },
    { id: 'support-main', source: 'support', target: 'main' },
];

test('resolves the exact outgoing menu handle', () => {
    const edge = resolveConnectionEdge(edges, {
        nodeId: 'main',
        handleId: 'nfe',
        direction: 'outgoing',
    });

    assert.equal(edge?.id, 'main-nfe');
    assert.equal(connectionDestination(edge, 'outgoing'), 'nfe');
});

test('uses optionKey when a legacy edge has no sourceHandle', () => {
    const edge = resolveConnectionEdge(edges, {
        nodeId: 'main',
        handleId: 'support',
        direction: 'outgoing',
    });

    assert.equal(edge?.id, 'main-support');
    assert.equal(connectionDestination(edge, 'outgoing'), 'support');
});

test('follows a simple outgoing handle and an incoming handle in reverse', () => {
    const outgoing = resolveConnectionEdge(edges, {
        nodeId: 'support',
        direction: 'outgoing',
    });
    const incoming = resolveConnectionEdge(edges, {
        nodeId: 'support',
        direction: 'incoming',
    });

    assert.equal(outgoing?.id, 'support-main');
    assert.equal(connectionDestination(outgoing, 'outgoing'), 'main');
    assert.equal(incoming?.id, 'main-support');
    assert.equal(connectionDestination(incoming, 'incoming'), 'main');
});

test('does not silently choose another menu option when the handle is disconnected', () => {
    const edge = resolveConnectionEdge(edges, {
        nodeId: 'main',
        handleId: 'missing',
        direction: 'outgoing',
    });

    assert.equal(edge, null);
});
