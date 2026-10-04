from __future__ import annotations

import importlib.util
import sys
from pathlib import Path

GENERATOR = Path(__file__).with_name("generate_flowcharts.py")


def load_generator():
    spec = importlib.util.spec_from_file_location("flowchart_generator", GENERATOR)
    if spec is None or spec.loader is None:
        raise RuntimeError("Unable to load flowchart generator")
    module = importlib.util.module_from_spec(spec)
    sys.modules[spec.name] = module
    spec.loader.exec_module(module)
    return module


def test_decision_outcomes_share_visual_rank() -> None:
    module = load_generator()
    failures: list[str] = []
    for diagram in module.DIAGRAMS:
        positions = module.layout_nodes(diagram)
        nodes = {node.id: node for level in diagram.levels for node in level}
        order = {node.id: index for index, node in enumerate(nodes.values())}
        for node in nodes.values():
            if node.kind != "decision":
                continue
            forward_targets = [
                edge.target
                for edge in diagram.edges
                if edge.source == node.id and order[edge.target] > order[node.id]
            ]
            target_y = {round(positions[target][1], 2) for target in forward_targets}
            if len(forward_targets) > 1 and len(target_y) != 1:
                failures.append(f"{diagram.title}: {node.label}")
    assert not failures, "Decision outcomes are not aligned: " + "; ".join(failures)


def test_all_graph_elements_stay_inside_safe_area() -> None:
    module = load_generator()
    failures: list[str] = []
    for diagram in module.DIAGRAMS:
        for node_id, (_, y, _, height) in module.layout_nodes(diagram).items():
            if y < 3.25 * module.cm or y + height > module.H - 3.8 * module.cm:
                failures.append(f"{diagram.title}:{node_id}")
    assert not failures, "Nodes outside safe area: " + "; ".join(failures)


if __name__ == "__main__":
    test_decision_outcomes_share_visual_rank()
    test_all_graph_elements_stay_inside_safe_area()
    print("flowchart layout tests passed")
