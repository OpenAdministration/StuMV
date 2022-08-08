{ pkgs ? import <nixpkgs> {} }:

with pkgs;

mkShell {
  buildInputs = [
    php80
    php80Packages.composer
    nodePackages.npm
    nodejs
  ];
}
