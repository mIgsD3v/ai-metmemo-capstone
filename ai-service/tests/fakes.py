"""Test doubles shared by the test modules: no network, no API keys."""

import hashlib
import math
import re

from openai import APIConnectionError


class FakeEmbedder:
    """Bag-of-words hashed into a small vector: texts sharing words land close together. No API calls."""

    calls = 0

    async def embed(self, texts):
        FakeEmbedder.calls += 1
        return [self.vector(text) for text in texts]

    @staticmethod
    def vector(text, size=128):
        v = [0.0] * size
        for word in re.findall(r"[a-z0-9]+", text.lower()):
            v[int(hashlib.md5(word.encode()).hexdigest(), 16) % size] += 1.0
        norm = math.sqrt(sum(x * x for x in v)) or 1.0
        return [x / norm for x in v]


class BrokenEmbedder:
    async def embed(self, texts):
        raise APIConnectionError(request=None)
