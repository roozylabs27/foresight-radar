import React, { useRef, useState } from "react";
import { Form, Input, Button, Modal, Typography, Alert, Space } from "antd";
import {
    DeleteOutlined,
    LockOutlined,
    ExclamationCircleOutlined,
} from "@ant-design/icons";
import { useForm } from "@inertiajs/react";

const { Title, Text, Paragraph } = Typography;

export default function DeleteUserForm({ className = "" }) {
    const [confirmingUserDeletion, setConfirmingUserDeletion] = useState(false);
    const passwordInput = useRef(null);

    const {
        data,
        setData,
        delete: destroy,
        processing,
        reset,
        errors,
    } = useForm({
        password: "",
    });

    const confirmUserDeletion = () => {
        setConfirmingUserDeletion(true);
    };

    const deleteUser = () => {
        destroy(route("profile.destroy"), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
            onError: () => passwordInput.current?.focus(),
            onFinish: () => reset(),
        });
    };

    const closeModal = () => {
        setConfirmingUserDeletion(false);
        reset();
    };

    return (
        <div className={className}>
            <div style={{ marginBottom: 16 }}>
                <Title level={4} style={{ margin: 0, color: "#cf1322" }}>
                    Hapus Akun Pengguna
                </Title>
                <Paragraph type="secondary" style={{ fontSize: 13, marginTop: 4 }}>
                    Setelah akun Anda dihapus, semua sumber daya dan data terkait akan
                    dihapus secara permanen. Harap unduh data atau informasi yang ingin
                    Anda simpan sebelum melanjutkan.
                </Paragraph>
            </div>

            <Button
                danger
                type="primary"
                icon={<DeleteOutlined />}
                onClick={confirmUserDeletion}
            >
                Hapus Akun Ini
            </Button>

            <Modal
                title={
                    <Space>
                        <ExclamationCircleOutlined style={{ color: "#ff4d4f" }} />
                        <span>Konfirmasi Penghapusan Akun</span>
                    </Space>
                }
                open={confirmingUserDeletion}
                onCancel={closeModal}
                footer={[
                    <Button key="cancel" onClick={closeModal} disabled={processing}>
                        Batal
                    </Button>,
                    <Button
                        key="delete"
                        danger
                        type="primary"
                        loading={processing}
                        onClick={deleteUser}
                    >
                        Hapus Akun Secara Permanen
                    </Button>,
                ]}
            >
                <Alert
                    type="error"
                    showIcon
                    style={{ marginBottom: 16 }}
                    message="Peringatan Tindakan Tidak Dapat Dibatalkan"
                    description="Semua data dan hak akses Anda di Foresight Radar akan dihapus secara permanen."
                />
                <Text style={{ fontSize: 13, display: "block", marginBottom: 12 }}>
                    Masukkan kata sandi Anda saat ini untuk mengonfirmasi bahwa Anda ingin
                    menghapus akun ini:
                </Text>
                <Form layout="vertical" requiredMark={true}>
                    <Form.Item
                        label="Kata Sandi Akun"
                        validateStatus={errors.password ? "error" : ""}
                        help={errors.password}
                        rules={[
                            {
                                required: true,
                                message: "Kata sandi wajib diisi untuk konfirmasi.",
                            },
                        ]}
                    >
                        <Input.Password
                            ref={passwordInput}
                            prefix={<LockOutlined style={{ color: "#8c8c8c" }} />}
                            size="large"
                            placeholder="Masukkan kata sandi Anda"
                            value={data.password}
                            onChange={(e) => setData("password", e.target.value)}
                            onPressEnter={deleteUser}
                        />
                    </Form.Item>
                </Form>
            </Modal>
        </div>
    );
}
