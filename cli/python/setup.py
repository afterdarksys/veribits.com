from setuptools import setup, find_packages

with open("README.md", "r", encoding="utf-8") as fh:
    long_description = fh.read()

setup(
    name="veribits",
    version="3.0.0",
    author="After Dark Systems, LLC",
    author_email="support@afterdarksys.com",
    description="VeriBits CLI - Professional Security and Developer Tools",
    long_description=long_description,
    long_description_content_type="text/markdown",
    url="https://www.veribits.com",
    project_urls={
        "Documentation": "https://www.veribits.com/cli.php",
        "Source": "https://github.com/afterdarksystems/veribits-cli",
    },
    py_modules=["veribits"],
    classifiers=[
        "Development Status :: 5 - Production/Stable",
        "Intended Audience :: Developers",
        "Intended Audience :: System Administrators",
        "Intended Audience :: Information Technology",
        "Topic :: Security",
        "Topic :: Software Development :: Quality Assurance",
        "License :: OSI Approved :: MIT License",
        "Programming Language :: Python :: 3",
        "Programming Language :: Python :: 3.8",
        "Programming Language :: Python :: 3.9",
        "Programming Language :: Python :: 3.10",
        "Programming Language :: Python :: 3.11",
        "Operating System :: OS Independent",
    ],
    python_requires=">=3.8",
    install_requires=[
        "requests>=2.28.0",
    ],
    entry_points={
        "console_scripts": [
            "veribits=veribits:main",
            "vb=veribits:main",  # Short alias
        ],
    },
)
